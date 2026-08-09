<?php

namespace app\server\model\order;

use app\common\model\order\Order as OrderModel;

/**
 * 订单模型
 */
class Order extends OrderModel
{
    /**
     * 订单列表
     */
    public function getOrderList($user_id)
    {
        $model = $this;
        // 获取数据列表
        $list = $model->with(['product.image', 'user'])
            ->where('user_id', '=', $user_id)
            ->where('pay_status', '=', 20)
            ->order(['create_time' => 'desc'])
            ->limit(5)
            ->select();
        foreach ($list as $item) {
            $item['pay_time'] = date('Y-m-d H:i:s', $item['pay_time']);
        }
        return $list;
    }


}