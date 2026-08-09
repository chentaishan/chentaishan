<?php

namespace app\common\enum\user\voucher;

use MyCLabs\Enum\Enum;

class VoucherLogSceneEnum extends Enum
{
    const EXCHANGE = 10;
    const ORDER_DEDUCT = 20;
    const ORDER_REFUND = 30;
    const ADMIN = 40;
    const CONTRIBUTION_UNLOCK = 50;
    const POOL_DIVIDEND = 60;
    const TRANSFER_OUT = 70;
    const TRANSFER_IN = 80;
    const BUY_DIGITAL = 90;
    const PARTNER_DIVIDEND = 100;
    const TEAM_DIVIDEND = 101;


    public static function data()
    {
        return [
            self::EXCHANGE => ['name' => '兑换', 'value' => self::EXCHANGE],
            self::ORDER_DEDUCT => ['name' => '订单抵扣', 'value' => self::ORDER_DEDUCT],
            self::ORDER_REFUND => ['name' => '订单退款退回', 'value' => self::ORDER_REFUND],
            self::ADMIN => ['name' => '管理员操作', 'value' => self::ADMIN],
            self::CONTRIBUTION_UNLOCK => ['name' => '贡献值解锁', 'value' => self::CONTRIBUTION_UNLOCK],
            self::POOL_DIVIDEND => ['name' => '消费券池发放', 'value' => self::POOL_DIVIDEND],
            self::TRANSFER_OUT => ['name' => '积分转出', 'value' => self::TRANSFER_OUT],
            self::TRANSFER_IN => ['name' => '积分转入', 'value' => self::TRANSFER_IN],
            self::BUY_DIGITAL => ['name' => '积分买数字资产', 'value' => self::BUY_DIGITAL],
            self::PARTNER_DIVIDEND => ['name' => '合伙人分红积分', 'value' => self::PARTNER_DIVIDEND],
            self::TEAM_DIVIDEND => ['name' => '团队分红积分', 'value' => self::TEAM_DIVIDEND],
        ];
    }
}
