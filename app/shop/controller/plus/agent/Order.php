<?php

namespace app\shop\controller\plus\agent;

use app\shop\controller\Controller;
use app\shop\model\plus\agent\Order as OrderModel;

/**
 * 分销订单
 */
class Order extends Controller
{

    /**
     * 分销订单列表
     */
    public function index()
    {
        $model = new OrderModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 订单导出
     */
    public function export($user_id = null, $is_settled = -1)
    {
        $model = new OrderModel();
        return $model->exportList($user_id, $is_settled);
    }

}