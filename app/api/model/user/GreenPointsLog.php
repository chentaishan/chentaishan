<?php

namespace app\api\model\user;

use app\common\model\user\GreenPointsLog as GreenPointsLogModel;

class GreenPointsLog extends GreenPointsLogModel
{
    public function getList($userId, $limit)
    {
        return $this->where('user_id', '=', $userId)
            ->order(['create_time' => 'desc'])
            ->paginate($limit);
    }
}
