<?php

namespace app\shop\controller\setting;

use app\common\model\hekangyuan\HekangyuanSetting as HekangyuanSettingModel;
use app\common\service\hekangyuan\DigitalAssetService;
use app\shop\controller\Controller;
use think\facade\Db;

/**
 * 福满堂配置：消费券公共池发放批次/明细、互转流水、买数字资产流水查询
 *
 * 通用比例(cloud_ratio 30/40-42/61-68)沿用 setting.rewardConfig/cloudRatioList、cloudRatioEdit。
 * 用户身份(白/黑/大众)与互转开关见 shop/user.user/setIdentity、setVoucherFreeTransfer。
 */
class RubyConfig extends Controller
{
    private function appId(): int
    {
        return (int)($this->store['app']['app_id'] ?? 0);
    }

    private function hasTable(string $table): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $table;
            return !empty(Db::query("SHOW TABLES LIKE '" . addslashes($fullName) . "'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 消费券池每日发放批次列表
     */
    public function voucherBatchList()
    {
        if (!$this->hasTable('voucher_dividend_batch')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_voucher.sql');
        }
        $list = Db::name('voucher_dividend_batch')
            ->where('app_id', '=', $this->appId())
            ->order('batch_id', 'desc')
            ->paginate($this->request->param());
        return $this->renderSuccess('', ['list' => $list]);
    }

    /**
     * 消费券池发放明细（可按 batch_id / 用户关键词筛选）
     */
    public function voucherLogList()
    {
        if (!$this->hasTable('voucher_dividend_log')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_voucher.sql');
        }
        $batchId = (int)$this->request->param('batch_id', 0);
        $query = Db::name('voucher_dividend_log')->alias('d')
            ->leftJoin('user u', 'd.user_id = u.user_id')
            ->where('d.app_id', '=', $this->appId());
        if ($batchId > 0) {
            $query->where('d.batch_id', '=', $batchId);
        }
        $search = trim((string)$this->request->param('search', ''));
        if ($search !== '') {
            $query->where('u.nickName|u.mobile', 'like', '%' . $search . '%');
        }
        $list = $query
            ->field(['d.*', 'u.nickName', 'u.mobile'])
            ->order('d.log_id', 'desc')
            ->paginate($this->request->param());
        return $this->renderSuccess('', ['list' => $list]);
    }

    /**
     * 消费券互转流水
     */
    public function transferList()
    {
        if (!$this->hasTable('voucher_transfer_log')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_voucher.sql');
        }
        $query = Db::name('voucher_transfer_log')->alias('t')
            ->leftJoin('user f', 't.from_user_id = f.user_id')
            ->leftJoin('user o', 't.to_user_id = o.user_id')
            ->where('t.app_id', '=', $this->appId());
        $search = trim((string)$this->request->param('search', ''));
        if ($search !== '') {
            $query->where('f.nickName|f.mobile|o.nickName|o.mobile', 'like', '%' . $search . '%');
        }
        $list = $query
            ->field([
                't.*',
                'f.nickName AS from_nick', 'f.mobile AS from_mobile',
                'o.nickName AS to_nick', 'o.mobile AS to_mobile',
            ])
            ->order('t.log_id', 'desc')
            ->paginate($this->request->param());
        return $this->renderSuccess('', ['list' => $list]);
    }

    /**
     * 消费券买数字资产流水
     */
    public function exchangeList()
    {
        if (!$this->hasTable('voucher_exchange_log')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_voucher.sql');
        }
        $query = Db::name('voucher_exchange_log')->alias('e')
            ->leftJoin('user u', 'e.user_id = u.user_id')
            ->where('e.app_id', '=', $this->appId());
        $search = trim((string)$this->request->param('search', ''));
        if ($search !== '') {
            $query->where('u.nickName|u.mobile', 'like', '%' . $search . '%');
        }
        $list = $query
            ->field(['e.*', 'u.nickName', 'u.mobile'])
            ->order('e.log_id', 'desc')
            ->paginate($this->request->param());
        return $this->renderSuccess('', ['list' => $list]);
    }

    /**
     * 获取数字资产币配置 + 实时币价概览
     */
    public function getDigitalSetting()
    {
        if (!$this->hasTable('hekangyuan_setting')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_digital_asset.sql');
        }
        $setting = HekangyuanSettingModel::getSetting($this->appId());
        $data = $setting->toArray();
        $data['current_price']  = DigitalAssetService::getCoinCurrentPrice($setting);
        $data['sell_fee_rate']  = DigitalAssetService::getSellFeeRate();
        return $this->renderSuccess('', ['setting' => $data]);
    }

    /**
     * 数字资产底池概况（轻量：仅底池/流通量/当前价/昨日价/初始价）
     */
    public function digitalPool()
    {
        if (!$this->hasTable('hekangyuan_setting')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_digital_asset.sql');
        }
        $setting = HekangyuanSettingModel::getSetting($this->appId());
        return $this->renderSuccess('', [
            'pool_amount'     => DigitalAssetService::truncate((float)($setting['pool_amount'] ?? 0), DigitalAssetService::ASSET_SCALE),
            'total_asset'     => DigitalAssetService::truncate((float)($setting['total_asset'] ?? 0), DigitalAssetService::ASSET_SCALE),
            'current_price'   => DigitalAssetService::getCoinCurrentPrice($setting),
            'yesterday_price' => DigitalAssetService::getCoinYesterdayPrice($setting),
            'init_price'      => DigitalAssetService::truncate((float)($setting['init_price'] ?? 0), DigitalAssetService::MONEY_SCALE),
        ]);
    }

    /**
     * 数字资产买卖流水（user_digital_asset_log，含买入/卖出价格、净额）
     * 可选筛选：action(10买入/20卖出)、search(昵称/手机号)
     */
    public function digitalAssetLog()
    {
        if (!$this->hasTable('user_digital_asset_log')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_digital_asset.sql');
        }
        $query = Db::name('user_digital_asset_log')->alias('l')
            ->leftJoin('user u', 'l.user_id = u.user_id')
            ->where('l.app_id', '=', $this->appId());
        $action = (int)$this->request->param('action', 0);
        if ($action > 0) {
            $query->where('l.action', '=', $action);
        }
        $search = trim((string)$this->request->param('search', ''));
        if ($search !== '') {
            $query->where('u.nickName|u.mobile', 'like', '%' . $search . '%');
        }
        $list = $query
            ->field(['l.*', 'u.nickName', 'u.mobile'])
            ->order('l.log_id', 'desc')
            ->paginate($this->request->param())
            ->each(function ($item) {
                $item['action_text'] = (int)$item['action'] === 20 ? '卖出' : '买入';
                return $item;
            });
        return $this->renderSuccess('', ['list' => $list]);
    }

    /**
     * 修改数字资产币配置
     * 可提交：init_price、in_pool_ratio、asset_ratio、sell_ratio、max_growth_multiple、
     *        min_exchange、max_exchange、is_open
     * 注意：pool_amount/total_asset/yesterday_price 由买卖与快照自动维护，不在此修改。
     */
    public function setDigitalSetting()
    {
        if (!$this->hasTable('hekangyuan_setting')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_digital_asset.sql');
        }
        $param   = $this->request->post();
        $setting = HekangyuanSettingModel::getSetting($this->appId());

        if (isset($param['init_price']) && $param['init_price'] <= 0) {
            return $this->renderError('初始价格必须大于0');
        }
        foreach (['in_pool_ratio', 'asset_ratio', 'sell_ratio'] as $field) {
            if (isset($param[$field]) && ($param[$field] < 0 || $param[$field] > 100)) {
                return $this->renderError('比例需在 0~100 之间');
            }
        }
        if (isset($param['max_growth_multiple']) && $param['max_growth_multiple'] < 0) {
            return $this->renderError('最大增长倍数不能为负数');
        }
        foreach (['min_exchange', 'max_exchange'] as $field) {
            if (isset($param[$field]) && $param[$field] < 0) {
                return $this->renderError('兑换限额不能为负数');
            }
        }
        $min = isset($param['min_exchange']) ? (float)$param['min_exchange'] : (float)$setting['min_exchange'];
        $max = isset($param['max_exchange']) ? (float)$param['max_exchange'] : (float)$setting['max_exchange'];
        if ($min > 0 && $max > 0 && $min > $max) {
            return $this->renderError('最小兑换额不能大于最大兑换额');
        }

        $data = [];
        foreach (['init_price', 'in_pool_ratio', 'asset_ratio', 'sell_ratio', 'max_growth_multiple', 'min_exchange', 'max_exchange'] as $field) {
            if (isset($param[$field])) {
                $data[$field] = $param[$field];
            }
        }
        if (isset($param['is_open'])) {
            $data['is_open'] = $param['is_open'] ? 1 : 0;
        }
        if (empty($data)) {
            return $this->renderError('没有需要修改的内容');
        }
        $data['update_time'] = time();
        $setting->save($data);
        return $this->renderSuccess('修改成功');
    }
}
