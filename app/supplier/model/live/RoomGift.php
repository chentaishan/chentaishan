<?php

namespace app\supplier\model\live;

use app\common\model\plus\live\RoomGift as RoomGiftModel;

/**
 * 房间模型
 */
class RoomGift extends RoomGiftModel
{
    /**
     * 列表
     */
    public function getList($params)
    {
        $model = $this;
        if(isset($params['room_id'])){
            $model = $model->where('room_id', '=', $params['room_id']);
        }
        return $model->order(['create_time' => 'asc'])
            ->paginate($params);
    }

}
