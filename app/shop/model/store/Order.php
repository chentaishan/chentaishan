<?php


namespace app\shop\model\store;

use app\common\model\store\Order as OrderModel;
use app\common\service\order\OrderService;

/**
 * 店员模型
 */
class Order extends OrderModel
{
    /**
     * 获取列表数据
     */
    public function getList($params)
    {
        $model = $this;
        if (isset($params['store_id']) && $params['store_id'] > 0) {
            $model = $model->where('clerk.store_id', '=', $params['store_id']);
        }
        if (!empty($params['search'])) {
            $model = $model->where('clerk.real_name', 'like', '%' . $params['search'] . '%');
        }
        if (isset($params['shop_supplier_id']) && $params['shop_supplier_id'] > 0) {
            $model = $model->where('order.shop_supplier_id', '=', $params['shop_supplier_id']);
        }
        if (isset($params['order_no']) && $params['order_no']) {
            $model = $model->where('o.order_no', 'like', '%' . $params['order_no'] . '%');
        }

        // 查询列表数据
        $data = $model->with(['store', 'clerk', 'supplier'])
            ->alias('order')
            ->field(['order.*'])
            ->join('store_clerk clerk', 'clerk.clerk_id = order.clerk_id', 'INNER')
            ->join('order o', 'o.order_id = order.order_id', 'INNER')
            ->order(['order.create_time' => 'desc'])
            ->paginate($params);
        if ($data->isEmpty()) {
            return $data;
        }
        // 整理订单信息
        return OrderService::getOrderList($data);
    }

}