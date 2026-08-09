<?php

namespace app\common\model\plus\live;

use app\common\model\BaseModel;

/**
 * 房间礼物模型
 */
class RoomGift extends BaseModel
{
    protected $name = 'live_room_gift';
    protected $pk = 'room_gift_id';

    /**
     * 关联封面图
     */
    public function cover()
    {
        return $this->hasOne('app\\common\\model\\file\\UploadFile', 'file_id', 'cover_img_id');
    }
}
