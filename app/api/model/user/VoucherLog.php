<?php

namespace app\api\model\user;

use app\common\model\user\VoucherLog as VoucherLogModel;

class VoucherLog extends VoucherLogModel
{
    public function getList($userId, $limit)
    {
        return $this->where('user_id', '=', $userId)
            ->order(['create_time' => 'desc'])
            ->paginate($limit);
    }
}
