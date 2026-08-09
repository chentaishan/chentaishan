<?php

namespace app\common\model\plus\live;

use app\common\model\BaseModel;

/**
 * 房间用户礼物模型
 */
class UserGift extends BaseModel
{
    protected $name = 'live_user_gift';
    protected $pk = 'user_gift_id';

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id')
            ->field(['user_id', 'nickName', 'avatarUrl']);
    }

    /**
     * 详情
     */
    public static function detail($room_id, $user_id)
    {
        return (new static())->where('room_id', '=', $room_id)
            ->where('user_id', '=', $user_id)
            ->find();
    }

    /**
     * 获取列表
     */
    public function getList($data)
    {
        return $this->with(['user'])
            ->where('room_id', '=', $data['room_id'])
            ->where('gift_num', '>', 0)
            ->order(['gift_num' => 'desc'])
            ->paginate($data);
    }
}
