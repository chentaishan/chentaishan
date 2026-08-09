<?php

namespace app\api\model\user;

use app\common\model\user\DigitalRightsLog as DigitalRightsLogModel;

class DigitalRightsLog extends DigitalRightsLogModel
{
    public function getList($userId, $limit)
    {
        return $this->where('user_id', '=', $userId)
            ->order(['create_time' => 'desc'])
            ->paginate($limit);
    }
}
