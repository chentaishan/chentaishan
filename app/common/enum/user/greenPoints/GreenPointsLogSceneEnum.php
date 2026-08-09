<?php

namespace app\common\enum\user\greenPoints;

use MyCLabs\Enum\Enum;

class GreenPointsLogSceneEnum extends Enum
{
    const ORDER_GIFT = 10;
    const EXCHANGE_VOUCHER = 20;
    const EXCHANGE_DIGITAL = 30;
    const ADMIN = 40;
    const REFUND = 50;

    public static function data()
    {
        return [
            self::ORDER_GIFT => ['name' => '确认收货赠送', 'value' => self::ORDER_GIFT],
            self::EXCHANGE_VOUCHER => ['name' => '兑换抵扣券', 'value' => self::EXCHANGE_VOUCHER],
            self::EXCHANGE_DIGITAL => ['name' => '兑换数权', 'value' => self::EXCHANGE_DIGITAL],
            self::ADMIN => ['name' => '管理员操作', 'value' => self::ADMIN],
            self::REFUND => ['name' => '售后扣回', 'value' => self::REFUND],
        ];
    }
}
