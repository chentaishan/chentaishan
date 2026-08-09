<?php

namespace app\shop\model\user;

use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\library\easywechat\AppMp;
use app\common\library\easywechat\AppWx;
use app\common\library\easywechat\WxPay;
use app\common\model\app\AppMp as AppMpModel;
use app\common\model\app\AppOpen as AppOpenModel;
use app\common\model\app\AppWx as AppWxModel;
use app\common\model\settings\Setting as SettingModel;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\Cash as CashModel;
use app\common\service\order\OrderService;
use think\facade\Db;
use app\shop\service\order\ExportService;

class Cash extends CashModel
{
    public function getAuditTimeAttr($value)
    {
        return $value > 0 ? date('Y-m-d H:i:s', $value) : 0;
    }

    public function getPayTypeAttr($value)
    {
        return ['text' => $this->payType[$value] ?? '', 'value' => $value];
    }

    public function getList($data)
    {
        $model = $this->alias('cash')
            ->with(['user'])
            ->field('cash.*, user.nickName, user.avatarUrl, user.real_name')
            ->join('user user', 'user.user_id = cash.user_id')
            ->order(['cash.create_time' => 'desc']);

        if (!empty($data['user_id'])) {
            $model = $model->where('cash.user_id', '=', $data['user_id']);
        }
        if (!empty($data['search'])) {
            $model = $model->where('user.nickName|user.mobile', 'like', '%' . $data['search'] . '%');
        }
        if (isset($data['apply_status']) && $data['apply_status'] > 0) {
            $model = $model->where('cash.apply_status', '=', $data['apply_status']);
        }
        if (isset($data['pay_type']) && $data['pay_type'] > 0) {
            $model = $model->where('cash.pay_type', '=', $data['pay_type']);
        }

        $list = $model->paginate($data);
        foreach ($list as &$item) {
            $item['cancelStatus'] = ($item['apply_status']['value'] == 50 && $item['pay_time'] + 86400 < time()) ? 1 : 0;
        }
        return $list;
    }

    public function submit($param)
    {
        if ($this['apply_status']['value'] != 10 && $this['apply_status']['value'] != 20) {
            $this->error = '状态错误';
            return false;
        }

        $this->startTrans();
        try {
            $data = [
                'apply_status' => $param['apply_status'],
                'audit_time' => time(),
            ];
            if ((int)$param['apply_status'] === 30) {
                $data['reject_reason'] = $param['reject_reason'] ?? '';
            }

            self::update($data, ['id' => $param['id']]);

            if ((int)$param['apply_status'] === 30) {
                User::backFreezeMoney($this['user_id'], $this['money']);
                BalanceLogModel::add(BalanceLogSceneEnum::CASH_BACK, [
                    'user_id' => $this['user_id'],
                    'money' => round((float)$this['money'], 2),
                    'app_id' => (int)($this['app_id'] ?: self::$app_id),
                ], '');
            }

            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    public function wechatPay0()
    {
        $user = User::detail($this['user_id']);
        $orderNO = OrderService::createOrderNo();
        $desc = '余额提现付款';
        $open_id = '';
        $app = null;

        if ($user['reg_source'] == 'mp') {
            $app = AppMp::getWxPayApp($user['app_id']);
            $open_id = $user['mpopen_id'];
        } elseif ($user['reg_source'] == 'wx') {
            $app = AppWx::getWxPayApp($user['app_id']);
            $open_id = $user['open_id'];
        }

        if ($open_id == '') {
            $this->error = '未找到用户open_id';
            return false;
        }

        $wxPay = new WxPay($app);
        if ($wxPay->transfers($orderNO, $open_id, $this['real_money'], $desc)) {
            $this->money();
            return true;
        }
        return false;
    }

    public function wechatPay()
    {
        if ($this['apply_status']['value'] != 20) {
            $this->error = '状态错误';
            return false;
        }

        $user = User::detail($this['user_id']);
        $orderNO = OrderService::createOrderNo();
        $desc = '余额提现付款';
        $open_id = '';
        $app_id = '';

        if ($this['source'] == 'mp') {
            $open_id = $user['mpopen_id'];
            $wxConfig = AppMpModel::getAppMpCache($app_id);
            $app_id = $wxConfig['mpapp_id'];
        } elseif ($this['source'] == 'wx') {
            $open_id = $user['open_id'];
            $wxConfig = AppWxModel::getAppWxCache($app_id);
            $app_id = $wxConfig['wxapp_id'];
        } elseif ($this['source'] == 'app') {
            $open_id = $user['appopen_id'];
            $wxConfig = AppOpenModel::getAppOpenCache($app_id);
            $app_id = $wxConfig['openapp_id'];
        }

        if ($open_id == '') {
            $this->error = '未找到用户open_id';
            return false;
        }

        $settings = SettingModel::getItem('balance_cash');
        $wxPay = new WxPay(null);
        $pars = [
            'real_name' => $user['real_name'],
            'appid' => $app_id,
            'desc' => $desc,
            'real_money' => $this['real_money'],
            'order_no' => $orderNO,
            'open_id' => $open_id,
            'scene_id' => $settings['scene_id'],
        ];
        $resArr = $wxPay->transfer($pars, $user['app_id']);
        if ($resArr['code'] == 0) {
            $this->error = $resArr['message'];
            return false;
        }

        if ($resArr['wx_cash_type'] == 1) {
            $this->save(['batch_id' => $resArr['batch_id']]);
            $this->money();
            return true;
        }

        return $this->save([
            'out_bill_no' => $resArr['out_bill_no'],
            'package_info' => $resArr['package_info'],
            'apply_status' => 50,
            'pay_time' => time(),
        ]);
    }

    public function getUserCashTotal()
    {
        return $this->where('apply_status', '=', 10)->count();
    }

    public function getUserApplyTotal()
    {
        return $this->where('apply_status', '=', 10)->count();
    }

    public function exportList($data)
    {
        $model = $this->alias('cash')
            ->with(['user'])
            ->field('cash.*, user.nickName, user.avatarUrl, user.mobile')
            ->join('user user', 'user.user_id = cash.user_id')
            ->order(['cash.create_time' => 'desc']);

        if (!empty($data['user_id'])) {
            $model = $model->where('cash.user_id', '=', $data['user_id']);
        }
        if (!empty($data['search'])) {
            $model = $model->where('user.nickName|user.mobile', 'like', '%' . $data['search'] . '%');
        }
        if (isset($data['apply_status']) && $data['apply_status'] > 0) {
            $model = $model->where('cash.apply_status', '=', $data['apply_status']);
        }
        if (isset($data['pay_type']) && $data['pay_type'] > 0) {
            $model = $model->where('cash.pay_type', '=', $data['pay_type']);
        }

        $list = $model->select();
        (new ExportService())->userCashList($list);
    }

    public function getPayType()
    {
        return [
            ['id' => '10', 'name' => '微信零钱'],
            ['id' => '20', 'name' => '支付宝'],
            ['id' => '30', 'name' => '银行卡'],
        ];
    }

    public function cancelPay()
    {
        $this->startTrans();
        try {
            if ($this['apply_status']['value'] != 50) {
                $this->error = '状态错误';
                return false;
            }
            $this->save([
                'apply_status' => 30,
                'reject_reason' => '微信付款已撤销',
            ]);
            User::backFreezeMoney($this['user_id'], $this['money']);
            BalanceLogModel::add(BalanceLogSceneEnum::CASH_BACK, [
                'user_id' => $this['user_id'],
                'money' => round((float)$this['money'], 2),
                'app_id' => (int)($this['app_id'] ?: self::$app_id),
            ], '');
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 提现总金额（从余额明细统计scene=60的提现扣减总额）
     */
    public function getCashTotalMoney()
    {
        return abs((float)Db::name('user_balance_log')
            ->where('scene', '=', 60)
            ->sum('money'));
    }

    //未提现总金额
    public function getUnCashTotalMoney()
    {
        return abs((float)Db::name('user')
            ->where('is_delete', 0)
            ->sum('balance'));

    }
}