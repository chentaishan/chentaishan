<?php

namespace app\common\model\user;

use app\common\enum\user\digitalRights\DigitalRightsLogSceneEnum;
use app\common\model\BaseModel;

class DigitalRightsLog extends BaseModel
{
    protected $name = 'user_digital_rights_log';
    protected $pk = 'log_id';
    protected $updateTime = false;

    public function user()
    {
        $module = self::getCalledModule() ?: 'common';
        return $this->belongsTo("app\\{$module}\\model\\user\\User");
    }

    public function getSceneAttr($value)
    {
        $map = DigitalRightsLogSceneEnum::data();
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
