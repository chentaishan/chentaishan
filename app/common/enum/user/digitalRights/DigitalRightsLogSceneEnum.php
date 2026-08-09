<?php

namespace app\common\enum\user\digitalRights;

use MyCLabs\Enum\Enum;

class DigitalRightsLogSceneEnum extends Enum
{
    const EXCHANGE = 10;
    const ADMIN = 20;

    public static function data()
    {
        return [
            self::EXCHANGE => ['name' => '绿色积分兑换', 'value' => self::EXCHANGE],
            self::ADMIN => ['name' => '管理员操作', 'value' => self::ADMIN],
        ];
    }
}
