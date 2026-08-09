<?php

namespace app\api\model\user;

use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\exception\BaseException;
use app\common\model\order\CloudCardApply;
use app\common\model\settings\WithdrawalConfig;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\Cash as CashModel;

class Cash extends CashModel
{
    protected $hidden = [
        'update_time',
    ];

    public function getList($user_id, $apply_status, $limit = 15)
    {
        $model = $this;
        $apply_status > -1 && $model = $model->where('apply_status', '=', $apply_status);
        return $model->where('user_id', '=', $user_id)
            ->order(['create_time' => 'desc'])
            ->paginate($limit);
    }

    public function submit($user, $data)
    {
        $this->validation($user, $data);
        $netRatio = $this->getCashNetRatio();
        $this->startTrans();
        try {
            $this->save(array_merge($data, [
                'user_id' => $user['user_id'],
                'apply_status' => 10,
                'app_id' => self::$app_id,
                'real_money' => round($data['money'] * $netRatio / 100, 2),
                'cash_ratio' => $netRatio,
            ]));

            CloudCardApply::create([
                'user_id' => $user['user_id'],
                'num' => round($data['money'] * $netRatio / 100, 2),
                'name' => $this->resolveApplyName($data),
                'card' => $this->resolveApplyCard($data),
                'bank' => $this->resolveApplyBank($data),
                'status' => 1,
                'content' => '',
                'check_time' => '',
                'check_user' => '',
            ]);

            $user->freezeMoney($data['money']);

            BalanceLogModel::add(BalanceLogSceneEnum::CASH, [
                'user_id' => $user['user_id'],
                'money' => -round((float)$data['money'], 2),
                'app_id' => self::$app_id,
            ], '');

            if ($data['pay_type'] == 10 && !$user['real_name']) {
                $user->save(['real_name' => $data['real_name']]);
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    private function validation($user, $data)
    {
        $wConfig = WithdrawalConfig::detail();
        $minMoney   = (float)$wConfig['min_amount'];
        $feePercent = (float)$wConfig['service_fee_percent'];

        if ($data['money'] <= 0) {
            throw new BaseException(['msg' => '提现金额不正确']);
        }
        if ($user['balance'] <= 0) {
            throw new BaseException(['msg' => '当前用户没有可提现余额']);
        }
        if ($data['money'] > $user['balance']) {
            throw new BaseException(['msg' => '提现金额不能大于可提现余额']);
        }
        if ($minMoney > 0 && $data['money'] < $minMoney) {
            throw new BaseException(['msg' => '最低提现金额为' . $minMoney . '元']);
        }

        if ($data['pay_type'] == 10) {
            if ($data['source'] != 'wx' && $data['source'] != 'mp') {
                throw new BaseException(['msg' => '当前客户端不允许提现到微信']);
            }
            if (empty($data['real_name'])) {
                throw new BaseException(['msg' => '请输入姓名']);
            }
        } elseif ($data['pay_type'] == 20) {
            if (empty($data['alipay_name']) || empty($data['alipay_account'])) {
                throw new BaseException(['msg' => '请补全提现信息']);
            }
        } elseif ($data['pay_type'] == 30) {
            if (empty($data['bank_name']) || empty($data['bank_account']) || empty($data['bank_card'])) {
                throw new BaseException(['msg' => '请补全提现信息']);
            }
        }
    }

    public function getCashNetRatio()
    {
        $wConfig  = WithdrawalConfig::detail();
        $feeRatio = (float)$wConfig['service_fee_percent'];
        $feeRatio = max(0, min(100, $feeRatio));
        return round(100 - $feeRatio, 2);
    }

    private function resolveApplyName($data)
    {
        if (!empty($data['bank_account'])) {
            return (string)$data['bank_account'];
        }
        if (!empty($data['alipay_name'])) {
            return (string)$data['alipay_name'];
        }
        if (!empty($data['real_name'])) {
            return (string)$data['real_name'];
        }
        if (!empty($data['name'])) {
            return (string)$data['name'];
        }
        return '';
    }

    private function resolveApplyCard($data)
    {
        if (!empty($data['bank_card'])) {
            return (string)$data['bank_card'];
        }
        if (!empty($data['alipay_account'])) {
            return (string)$data['alipay_account'];
        }
        if (!empty($data['card'])) {
            return (string)$data['card'];
        }
        return '';
    }

    private function resolveApplyBank($data)
    {
        if (!empty($data['bank_name'])) {
            return (string)$data['bank_name'];
        }
        if ((int)$data['pay_type'] === 20) {
            return '支付宝';
        }
        if ((int)$data['pay_type'] === 10) {
            return '微信';
        }
        if (!empty($data['bank'])) {
            return (string)$data['bank'];
        }
        return '';
    }

    public function receiptMoney()
    {
        if ($this['apply_status']['value'] != 50) {
            $this->error = '状态错误，不允许收款';
            return false;
        }
        $result = $this->money();
        if (!$result) {
            $this->error = '收款失败';
            return false;
        }
        return true;
    }
}