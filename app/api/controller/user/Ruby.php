<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\common\model\hekangyuan\HekangyuanSetting as HekangyuanSettingModel;
use app\common\model\hekangyuan\UserDigitalAsset as UserDigitalAssetModel;
use app\common\model\hekangyuan\UserDigitalAssetLog as UserDigitalAssetLogModel;
use app\common\model\user\User as UserModel;
use app\common\service\activity\EnergyRewardService;
use app\common\service\hekangyuan\DigitalAssetService;
use app\common\service\hekangyuan\DigitalExchangeService;
use app\common\service\hekangyuan\VoucherPackService;
use app\common\service\hekangyuan\VoucherTransferService;
use think\facade\Db;

/**
 * 福满堂 C 端接口：资产总览 / 贡献值记录 / 消费券流水 / 消费券池发放记录 / 互转 / 买数字资产
 */
class Ruby extends Controller
{
    private $user;

    public function initialize()
    {
        parent::initialize();
        $this->user = $this->getUser();
    }

    private function userId(): int
    {
        return (int)($this->user['user_id'] ?? 0);
    }

    private function appId(): int
    {
        return (int)($this->user['app_id'] ?? 0);
    }

    private function pageParams(): array
    {
        $page = max(1, (int)$this->request->param('page', 1));
        $size = (int)$this->request->param('page_size', 20);
        if ($size <= 0) {
            $size = 20;
        }
        if ($size > 100) {
            $size = 100;
        }
        return [$page, $size];
    }

    /**
     * 资产总览：余额/消费券/消费券包/数字资产 + 身份/互转开关 + 贡献值解锁进度
     */
    public function asset()
    {
        $userId = $this->userId();
        $row = Db::name('user')
            ->where('user_id', '=', $userId)
            ->field(['balance', 'f_level','is_partner', 'voucher', 'digital_rights', 'voucher_pack_cap', 'voucher_pack_released', 'hky_identity', 'voucher_free_transfer'])
            ->find() ?: [];

        $energy   = (new EnergyRewardService())->getEnergyAssetSummary($userId);
        $remainCap = (new VoucherPackService())->remainCap($row);
        $identity  = (int)($row['hky_identity'] ?? 0);

        $data = [
            'balance'               => $this->money($row['balance'] ?? 0),
            'voucher'               => $this->money($row['voucher'] ?? 0),
            'digital_rights'        => $this->money($row['digital_rights'] ?? 0),
            'voucher_pack_cap'      => $this->money($row['voucher_pack_cap'] ?? 0),
            'voucher_pack_released' => $this->money($row['voucher_pack_released'] ?? 0),
            'voucher_pack_remain'   => $this->money($remainCap),
            'hky_identity'          => $identity,
            'hky_identity_text'     => [0 => '大众', 1 => '白名单', 2 => '黑名单'][$identity] ?? '大众',
            'voucher_free_transfer' => (int)($row['voucher_free_transfer'] ?? 0),
            'is_partner'            => $row['is_partner'],
            'f_level'            => $row['f_level'],
            // 贡献值不作为独立资产展示；这里只返回贡献值解锁进度，解锁后进入消费券。
            'contribution'          => [
                'pending_total'   => $energy['energy_pending_total'] ?? '0.00',
                'releasing_total' => $energy['energy_releasing_total'] ?? '0.00',
                'settled_total'   => $energy['energy_settled_total'] ?? '0.00',
                'progress_total_percent'    => $energy['energy_progress_total_percent'] ?? '0',
                'progress_released_percent' => $energy['energy_progress_released_percent'] ?? '0',
                'multiplier'      => $energy['energy_multiplier'] ?? '0',
            ],
            'energy_progress_total_percent'    => $energy['energy_progress_total_percent'] ?? '0',
            'energy_progress_released_percent' => $energy['energy_progress_released_percent'] ?? '0',
        ];
        return $this->renderSuccess('', $data);
    }

    /**
     * 贡献值记录列表（兼容旧能量记录表）
     * GET: status(0全部 1待释放 2释放中 3已释放), page, page_size
     */
    public function contributionList()
    {
        $userId = $this->userId();
        [$page, $size] = $this->pageParams();
        $status = (int)$this->request->param('status', 0);
        $data = (new EnergyRewardService())->getEnergyRecordList($userId, $status, $page, $size);
        return $this->renderSuccess('', $data);
    }

    /**
     * 消费券流水（贡献值解锁/池发放/互转/买数字资产/订单抵扣等）
     */
    public function voucherLogList()
    {
        $userId = $this->userId();
        [$page, $size] = $this->pageParams();

        $total = (int)Db::name('user_voucher_log')->where('user_id', '=', $userId)->count();
        $list  = Db::name('user_voucher_log')
            ->where('user_id', '=', $userId)
            ->field(['log_id', 'scene', 'value', 'describe', 'remark', 'create_time'])
            ->order('log_id', 'desc')
            ->page($page, $size)
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item['create_time_text'] = $item['create_time'] ? date('Y-m-d H:i:s', (int)$item['create_time']) : '';
        }
        unset($item);

        return $this->renderSuccess('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 我的消费券池发放记录
     */
    public function poolDividendList()
    {
        $userId = $this->userId();
        [$page, $size] = $this->pageParams();

        $total = (int)Db::name('voucher_dividend_log')->where('user_id', '=', $userId)->count();
        $list  = Db::name('voucher_dividend_log')->alias('d')
            ->leftJoin('voucher_dividend_batch b', 'd.batch_id = b.batch_id')
            ->where('d.user_id', '=', $userId)
            ->field(['d.log_id', 'd.batch_id', 'd.identity', 'd.self_units', 'd.push_units', 'd.weight', 'd.calc_amount', 'd.actual_amount', 'd.create_time', 'b.stat_date'])
            ->order('d.log_id', 'desc')
            ->page($page, $size)
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item['identity_text']    = [0 => '大众', 1 => '白名单', 2 => '黑名单'][(int)$item['identity']] ?? '大众';
            $item['create_time_text'] = $item['create_time'] ? date('Y-m-d H:i:s', (int)$item['create_time']) : '';
        }
        unset($item);

        $settledTotal = round((float)Db::name('voucher_dividend_log')->where('user_id', '=', $userId)->sum('actual_amount'), 2);

        return $this->renderSuccess('', [
            'list'          => $list,
            'total'         => $total,
            'settled_total' => $this->money($settledTotal),
        ]);
    }

    /**
     * 消费券互转
     * POST: to_mobile 或 to_user_id, amount, pay_password
     */
    public function transfer()
    {
        $userId   = $this->userId();
        $amount   = (float)$this->request->post('amount', 0);
        $toUserId = (int)$this->request->post('to_user_id', 0);
        $toMobile = trim((string)$this->request->post('to_mobile', ''));
        $payPassword = trim((string)$this->request->post('pay_password', ''));

        $payPasswordError = UserModel::getPayPasswordVerifyError($this->user, $payPassword);
        if ($payPasswordError !== null) {
            return $this->renderError($payPasswordError);
        }

        if ($toUserId <= 0 && $toMobile !== '') {
            $toUserId = (int)Db::name('user')
                ->where('mobile', '=', $toMobile)
                ->where('app_id', '=', $this->appId())
                ->where('is_delete', '=', 0)
                ->value('user_id');
        }
        if ($toUserId <= 0) {
            return $this->renderError('收款用户不存在');
        }

        $service = new VoucherTransferService();
        $result  = $service->transfer($userId, $toUserId, $amount);
        if ($result === null) {
            return $this->renderError($service->getError() ?: '转出失败');
        }
        return $this->renderSuccess('转出成功', $result);
    }

    /**
     * 消费券买（转换）数字资产
     * POST: amount
     */
    public function exchangeDigital()
    {
        $userId = $this->userId();
        $amount = (float)$this->request->post('amount', 0);

        $service = new DigitalExchangeService();
        $result  = $service->exchange($userId, $amount);
        if ($result === null) {
            return $this->renderError($service->getError() ?: '兑换失败');
        }
        return $this->renderSuccess('兑换成功', $result);
    }

    /**
     * 我的互转流水
     */
    public function transferList()
    {
        $userId = $this->userId();
        [$page, $size] = $this->pageParams();

        $query = Db::name('voucher_transfer_log')
            ->where('from_user_id', '=', $userId)
            ->whereOr('to_user_id', '=', $userId);
        $total = (int)Db::name('voucher_transfer_log')
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', '=', $userId)->whereOr('to_user_id', '=', $userId);
            })->count();
        $list = Db::name('voucher_transfer_log')
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', '=', $userId)->whereOr('to_user_id', '=', $userId);
            })
            ->order('log_id', 'desc')
            ->page($page, $size)
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item['direction']        = (int)$item['from_user_id'] === $userId ? 'out' : 'in';
            $item['create_time_text'] = $item['create_time'] ? date('Y-m-d H:i:s', (int)$item['create_time']) : '';
        }
        unset($item);

        return $this->renderSuccess('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 我的买数字资产流水
     */
    public function exchangeList()
    {
        $userId = $this->userId();
        [$page, $size] = $this->pageParams();

        $total = (int)Db::name('voucher_exchange_log')->where('user_id', '=', $userId)->count();
        $list  = Db::name('voucher_exchange_log')
            ->where('user_id', '=', $userId)
            ->order('log_id', 'desc')
            ->page($page, $size)
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item['create_time_text'] = $item['create_time'] ? date('Y-m-d H:i:s', (int)$item['create_time']) : '';
        }
        unset($item);

        return $this->renderSuccess('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 数字资产首页：币价/配置 + 我的持仓概况
     */
    public function digitalInfo()
    {
        $userId  = $this->userId();
        $appId   = $this->appId();
        $setting = HekangyuanSettingModel::getSetting($appId);

        $current   = DigitalAssetService::getCoinCurrentPrice($setting);
        $yesterday = DigitalAssetService::getCoinYesterdayPrice($setting);
        $delta     = $current - $yesterday;
        $rate      = $yesterday > 0 ? round($delta / $yesterday * 100, 2) : 0;
        $feeRate   = DigitalAssetService::getSellFeeRate();

        // 我的持仓汇总（剩余持仓总量 + 按当前有效价估算卖出净额）
        $assets = UserDigitalAssetModel::where('user_id', '=', $userId)
            ->where('app_id', '=', $appId)
            ->where('status', '=', UserDigitalAssetModel::STATUS_HOLD)
            ->where('remain_qty', '>', 0)
            ->select();
        $totalQty = 0;
        $estNet   = 0;
        foreach ($assets as $asset) {
            $price = DigitalAssetService::effectivePrice($current, (float)$asset['buy_price'], $setting);
            $gross = (float)$asset['remain_qty'] * $price * (float)$setting['sell_ratio'] / 100;
            $totalQty += (float)$asset['remain_qty'];
            $estNet   += $gross * (1 - $feeRate / 100);
        }

        return $this->renderSuccess('', [
            'is_open'             => (int)$setting['is_open'],
            'current_price'       => $current,
            'yesterday_price'     => $yesterday,
            'change'              => DigitalAssetService::truncate($delta, DigitalAssetService::MONEY_SCALE),
            'change_rate'         => $rate,
            'sell_ratio'          => (float)$setting['sell_ratio'],
            'max_growth_multiple' => (float)$setting['max_growth_multiple'],
            'min_exchange'        => $this->money($setting['min_exchange'] ?? 0),
            'max_exchange'        => $this->money($setting['max_exchange'] ?? 0),
            'sell_fee_rate'       => $feeRate,
            'my_total_qty'        => DigitalAssetService::truncate($totalQty, DigitalAssetService::ASSET_SCALE),
            'my_estimate_net'     => DigitalAssetService::truncate($estNet, DigitalAssetService::MONEY_SCALE),
        ]);
    }

    /**
     * 我的数字资产持仓列表（按笔，分页）
     */
    public function digitalAssetList()
    {
        $userId  = $this->userId();
        $appId   = $this->appId();
        [$page, $size] = $this->pageParams();
        $setting = HekangyuanSettingModel::getSetting($appId);
        $current = DigitalAssetService::getCoinCurrentPrice($setting);

        $total = (int)UserDigitalAssetModel::where('user_id', '=', $userId)->where('app_id', '=', $appId)->count();
        $list  = UserDigitalAssetModel::where('user_id', '=', $userId)
            ->where('app_id', '=', $appId)
            ->order('asset_id', 'desc')
            ->page($page, $size)
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item['current_price']    = DigitalAssetService::effectivePrice($current, (float)$item['buy_price'], $setting);
            $item['yesterday_price']  = DigitalAssetService::getCoinYesterdayPrice($setting);
            $item['create_time_text'] = $item['create_time'] ?? '';
        }
        unset($item);

        $totalQty = (float)UserDigitalAssetModel::where('user_id', '=', $userId)
            ->where('app_id', '=', $appId)
            ->where('status', '=', UserDigitalAssetModel::STATUS_HOLD)
            ->where('remain_qty', '>', 0)
            ->sum('remain_qty');

        return $this->renderSuccess('', [
            'list'      => $list,
            'total'     => $total,
            'total_qty' => DigitalAssetService::truncate($totalQty, DigitalAssetService::ASSET_SCALE),
        ]);
    }

    /**
     * 数字资产持仓详情 + 流水
     */
    public function digitalAssetDetail()
    {
        $userId  = $this->userId();
        $appId   = $this->appId();
        $assetId = (int)$this->request->param('asset_id', 0);
        $setting = HekangyuanSettingModel::getSetting($appId);

        $asset = UserDigitalAssetModel::where('asset_id', '=', $assetId)
            ->where('user_id', '=', $userId)
            ->where('app_id', '=', $appId)
            ->find();
        if (empty($asset)) {
            return $this->renderError('资产记录不存在');
        }
        $current = DigitalAssetService::getCoinCurrentPrice($setting);
        $assetArr = $asset->toArray();
        $assetArr['current_price']   = DigitalAssetService::effectivePrice($current, (float)$asset['buy_price'], $setting);
        $assetArr['yesterday_price'] = DigitalAssetService::getCoinYesterdayPrice($setting);

        $logs = UserDigitalAssetLogModel::where('asset_id', '=', $assetId)
            ->order('log_id', 'desc')
            ->select();

        return $this->renderSuccess('', ['asset' => $assetArr, 'logs' => $logs]);
    }

    /**
     * 卖出数字资产（按笔，净额进余额）
     * POST: asset_id, num
     */
    public function digitalSell()
    {
        $userId  = $this->userId();
        $assetId = (int)$this->request->post('asset_id', 0);
        $num     = (float)$this->request->post('num', 0);

        $service = new DigitalAssetService();
        $result  = $service->sellAsset($this->user, $assetId, $num, $this->appId());
        if ($result === null) {
            return $this->renderError($service->getError() ?: '卖出失败');
        }
        return $this->renderSuccess('卖出成功', $result);
    }

    /**
     * 我的数字资产流水（买入/卖出）
     */
    public function digitalAssetLog()
    {
        $userId = $this->userId();
        [$page, $size] = $this->pageParams();

        $total = (int)UserDigitalAssetLogModel::where('user_id', '=', $userId)->where('app_id', '=', $this->appId())->count();
        $list  = UserDigitalAssetLogModel::where('user_id', '=', $userId)
            ->where('app_id', '=', $this->appId())
            ->order('log_id', 'desc')
            ->page($page, $size)
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item['create_time_text'] = $item['create_time'] ?? '';
        }
        unset($item);

        return $this->renderSuccess('', ['list' => $list, 'total' => $total]);
    }

    private function money($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }
}
