<?php

namespace app\common\model\user;

use app\common\enum\user\voucher\VoucherLogSceneEnum;
use app\common\model\BaseModel;

class VoucherLog extends BaseModel
{
    protected $name = 'user_voucher_log';
    protected $pk = 'log_id';
    protected $updateTime = false;

    public function user()
    {
        $module = self::getCalledModule() ?: 'common';
        return $this->belongsTo("app\\{$module}\\model\\user\\User");
    }

    public function getSceneAttr($value)
    {
        $map = VoucherLogSceneEnum::data();
        return [
            'text'  => $map[$value]['name'] ?? '',
            'value' => (int)$value,
        ];
    }

    public static function add(array $data)
    {
        $static = new static;
        $static->save(array_merge([
            'app_id'      => $static::$app_id,
            'create_time' => time(),
        ], $data));
    }
}
