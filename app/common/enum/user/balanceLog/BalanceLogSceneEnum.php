<?php

namespace app\common\enum\user\balanceLog;

use MyCLabs\Enum\Enum;

class BalanceLogSceneEnum extends Enum
{
    const RECHARGE = 10;
    const CONSUME = 20;
    const ADMIN = 30;
    const REFUND = 40;
    const POINTS = 50;
    const CASH = 60;
    const CASH_BACK = 61;
    const LOTTERY = 70;
    const TRANSFER = 80;
    const EXCHANGE = 90;
    const REWARD = 100;
    const POINTS_TO_BALANCE = 110;
    const BALANCE_TO_POINTS = 120;
    const WITHDRAW = 130;
    const WITHDRAW_REFUND = 131;

    public static function data()
    {
        return [
            self::RECHARGE => [
                'name' => '用户充值',
                'value' => self::RECHARGE,
                'describe' => '用户充值：%s',
            ],
            self::CONSUME => [
                'name' => '用户消费',
                'value' => self::CONSUME,
                'describe' => '用户消费：%s',
            ],
            self::ADMIN => [
                'name' => '管理员操作',
                'value' => self::ADMIN,
                'describe' => '后台管理员[%s]操作',
            ],
            self::REFUND => [
                'name' => '订单退款',
                'value' => self::REFUND,
                'describe' => '订单退款：%s',
            ],
            self::POINTS => [
                'name' => '积分转换',
                'value' => self::POINTS,
                'describe' => '积分转换余额',
            ],
            self::CASH => [
                'name' => '余额提现',
                'value' => self::CASH,
                'describe' => '余额提现',
            ],
            self::CASH_BACK => [
                'name' => '提现驳回退回',
                'value' => self::CASH_BACK,
                'describe' => '提现驳回退回',
            ],
            self::LOTTERY => [
                'name' => '抽奖获取',
                'value' => self::LOTTERY,
                'describe' => '抽奖获取',
            ],
            self::TRANSFER => [
                'name' => '余额互转',
                'value' => self::TRANSFER,
                'describe' => '余额互转：%s',
            ],
            self::EXCHANGE => [
                'name' => '余额兑换',
                'value' => self::EXCHANGE,
                'describe' => '余额兑换：%s',
            ],
            self::REWARD => [
                'name' => '奖励发放',
                'value' => self::REWARD,
                'describe' => '奖励发放：%s',
            ],
            self::POINTS_TO_BALANCE => [
                'name' => '积分转余额',
                'value' => self::POINTS_TO_BALANCE,
                'describe' => '积分转余额：%s',
            ],
            self::BALANCE_TO_POINTS => [
                'name' => '余额转积分',
                'value' => self::BALANCE_TO_POINTS,
                'describe' => '余额转积分：%s',
            ],
            self::WITHDRAW => [
                'name' => '申请提现',
                'value' => self::WITHDRAW,
                'describe' => '申请提现：%s',
            ],
            self::WITHDRAW_REFUND => [
                'name' => '提现驳回退回',
                'value' => self::WITHDRAW_REFUND,
                'describe' => '提现驳回退回：%s',
            ],
        ];
    }
}