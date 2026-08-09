<?php

namespace app\common\model\user;

use app\common\enum\user\greenPoints\GreenPointsLogSceneEnum;
use app\common\model\BaseModel;

class GreenPointsLog extends BaseModel
{
    protected $name = 'user_green_points_log';
    protected $pk = 'log_id';
    protected $updateTime = false;

    public function user()
    {
        $module = self::getCalledModule() ?: 'common';
        return $this->belongsTo("app\\{$module}\\model\\user\\User");
    }

    public function getSceneAttr($value)
    {
        $map = GreenPointsLogSceneEnum::data();
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

    public function onBatchAdd(array $saveData)
    {
        $now = time();
        foreach ($saveData as &$row) {
            $row['create_time'] = $row['create_time'] ?? $now;
        }
        return $this->saveAll($saveData);
    }
}
