<?php

namespace app\shop\controller\plus\live;

use app\shop\controller\Controller;
use app\shop\model\order\Order as OrderModel;
/**
 * 直播商品订单控制器
 */
class Order extends Controller
{

    
    /**
     * 列表
     */
    public function list()
    {
        $model = new OrderModel();
        $list = $model->getAgentLiveOrder($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    
}