<?php

namespace app\shop\controller\store;

use app\shop\controller\Controller;
use app\shop\model\store\Store as StoreModel;
use app\shop\model\store\Order as OrderModel;
use app\shop\model\supplier\Supplier as SupplierModel;

/**
 * 订单核销控制器
 */
class Order extends Controller
{
    /**
     * 订单核销记录列表
     */
    public function index()
    {
        $data = $this->postData();
        // 核销记录列表
        $model = new OrderModel;
        $list = $model->getList($data);
        // 门店列表
        $store_list = (new StoreModel)->getAllList();
        //商户列表
        $supplierList = SupplierModel::getAll();
        return $this->renderSuccess('', compact('list', 'store_list', 'supplierList'));
    }
}
