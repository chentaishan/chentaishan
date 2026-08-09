<?php

namespace app\common\model\plus\live;

use app\common\model\BaseModel;

/**
 * 礼物模型
 */
class Gift extends BaseModel
{
    protected $name = 'live_gift';
    protected $pk = 'gift_id';

    /**
     * 关联封面图
     */
    public function image()
    {
        return $this->hasOne('app\\common\\model\\file\\UploadFile', 'file_id', 'image_id')
            ->bind(['file_path', 'file_name', 'file_url']);
    }

    /**
     * 详情
     */
    public static function detail($gift_id, $with = [])
    {
        return (new static())->with($with)->find($gift_id);
    }

}
