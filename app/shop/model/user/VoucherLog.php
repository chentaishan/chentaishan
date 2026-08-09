<?php

namespace app\shop\model\user;

use app\common\model\user\VoucherLog as VoucherLogModel;

class VoucherLog extends VoucherLogModel
{
    public function getList($query = [])
    {
        $model = $this;
        if (!empty($query['search'])) {
            $model = $model->where('user.nickName|user.mobile', 'like', '%' . trim($query['search']) . '%');
        }
        if (!empty($query['value1'])) {
            $sta_time = array_shift($query['value1']);
            $end_time = array_pop($query['value1']);
            $model = $model->whereBetweenTime('log.create_time', $sta_time, date('Y-m-d 23:59:59', strtotime($end_time)));
        }
        return $model->with(['user'])
            ->alias('log')
            ->field('log.*')
            ->join('user', 'user.user_id = log.user_id')
            ->order(['log.create_time' => 'desc'])
            ->paginate($query);
    }
}
