<?php

namespace app\supplier\model\plus\agent;

use app\common\model\plus\agent\Order as OrderModel;
use app\common\service\order\OrderService;

/**
 * 分销商订单模型
 */
class Order extends OrderModel
{
    /**
     * 获取分销商订单列表
     */
    public function getList($shop_supplier_id, $params)
    {
        $model = $this;
        if (isset($params['is_settled']) && $params['is_settled'] > -1) {
            $model = $model->where('is_settled', '=', $params['is_settled']);
        }
        // 获取分销商订单列表
        $data = $model->with([
            'agent_first',
            'agent_second',
            'agent_third'
        ])->where('shop_supplier_id', '=', $shop_supplier_id)
            ->order(['create_time' => 'desc'])
            ->paginate($params);
        if ($data->isEmpty()) {
            return $data;
        }
        // 获取订单的主信息
        $with = ['product' => ['image', 'refund'], 'address', 'user'];
        return OrderService::getOrderList($data, 'order_master', $with);
    }

}