<?php

namespace app\supplier\controller\order;

use app\supplier\controller\Controller;
use app\supplier\model\plus\agent\Order as OrderModel;

/**
 * 分销订单控制器
 */
class Agent extends Controller
{
    /**
     * 订单列表
     */
    public function index()
    {
        // 订单列表
        $model = new OrderModel();
        $list = $model->getList($this->getSupplierId(), $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

}