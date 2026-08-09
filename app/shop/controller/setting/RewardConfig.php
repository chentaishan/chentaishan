<?php

namespace app\shop\controller\setting;

use app\shop\controller\Controller;
use app\common\service\settings\WithdrawalConfigService;
use think\facade\Db;

/**
 * 奖励配置：到账比例、提现、cloud_ratio
 */
class RewardConfig extends Controller
{
    /**
     * 奖励到账比例配置（余额/积分）
     */
    public function index()
    {
        $appId = $this->store['app']['app_id'] ?? 10001;

        if ($this->request->isGet()) {
            $rows = Db::name('reward_config')
                ->where('app_id', '=', $appId)
                ->select()
                ->toArray();
            $map = [];
            foreach ($rows as $row) {
                $map[$row['config_key']] = $row['config_value'];
            }
            return $this->renderSuccess('', compact('map'));
        }

        $balanceRatio = $this->request->param('balance_ratio', '');
        $pointsRatio  = $this->request->param('points_ratio', '');

        if ($balanceRatio === '' || $pointsRatio === '') {
            return $this->renderError('请填写完整');
        }

        $br = (float)$balanceRatio;
        $pr = (float)$pointsRatio;
        if ($br < 0 || $pr < 0 || ($br + $pr) != 100) {
            return $this->renderError('余额比例 + 积分比例 必须等于 100');
        }

        $now = time();
        $this->upsertConfig('balance_ratio', (string)$br, '奖励到账余额比例%', $appId, $now);
        $this->upsertConfig('points_ratio', (string)$pr, '奖励到账积分比例%', $appId, $now);

        return $this->renderSuccess('操作成功');
    }

    private function upsertConfig($key, $value, $remark, $appId, $now)
    {
        $exists = Db::name('reward_config')
            ->where('config_key', '=', $key)
            ->where('app_id', '=', $appId)
            ->find();
        if ($exists) {
            Db::name('reward_config')
                ->where('id', '=', $exists['id'])
                ->update([
                    'config_value' => $value,
                    'update_time'  => $now,
                ]);
        } else {
            Db::name('reward_config')->insert([
                'config_key'   => $key,
                'config_value' => $value,
                'remark'       => $remark,
                'app_id'       => $appId,
                'create_time'  => $now,
                'update_time'  => $now,
            ]);
        }
    }

    /**
     * 获取奖励相关配置汇总
     */
    public function getAllConfig()
    {
        $cloudRatios = Db::name('cloud_ratio')->select()->toArray();

        $data = [
            'withdrawal'   => WithdrawalConfigService::get(),
            'cloud_ratios' => $cloudRatios,
        ];
        return $this->renderSuccess('', compact('data'));
    }

    public function withdrawalConfig()
    {
        $data = WithdrawalConfigService::get();
        return $this->renderSuccess('', compact('data'));
    }

    public function withdrawalConfigEdit()
    {
        $minAmount      = $this->request->post('min_amount');
        $servicePercent = $this->request->post('service_fee_percent');

        if ($minAmount !== null && $minAmount !== '' && (float)$minAmount < 0) {
            return $this->renderError('最低提现金额不能为负');
        }
        if ($servicePercent !== null && $servicePercent !== ''
            && ((float)$servicePercent < 0 || (float)$servicePercent > 100)) {
            return $this->renderError('手续费比例范围0-100');
        }

        try {
            WithdrawalConfigService::update(
                $minAmount !== null && $minAmount !== '' ? (float)$minAmount : null,
                $servicePercent !== null && $servicePercent !== '' ? (float)$servicePercent : null
            );
            return $this->renderSuccess('操作成功');
        } catch (\Exception $e) {
            return $this->renderError('操作失败：' . $e->getMessage());
        }
    }

    public function cloudRatioList()
    {
        $list = Db::name('cloud_ratio')->where('status', '=', 0)->select()->toArray();
        return $this->renderSuccess('', compact('list'));
    }

    public function cloudRatioEdit()
    {
        $ratioId = intval($this->request->post('ratio_id'));
        if ($ratioId <= 0) {
            return $this->renderError('配置ID无效');
        }

        $row = Db::name('cloud_ratio')->where('ratio_id', '=', $ratioId)->find();
        if (!$row) {
            return $this->renderError('未找到该配置项');
        }

        $num = $this->request->post('num');
        if ($num === null || $num === '') {
            return $this->renderError('请填写配置值');
        }

        Db::startTrans();
        try {
            Db::name('cloud_ratio')
                ->where('ratio_id', '=', $ratioId)
                ->update([
                    'num'         => $num,
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            Db::commit();
            return $this->renderSuccess('操作成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->renderError('操作失败：' . $e->getMessage());
        }
    }
}
