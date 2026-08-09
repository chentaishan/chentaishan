<?php

namespace app\shop\controller\supplier;

use app\shop\controller\Controller;
use app\shop\model\supplier\DepositOrder as DepositOrderModel;
use app\shop\model\supplier\Supplier as SupplierModel;

/**
 * 供应商押金订单控制器
 */
class Order extends Controller
{

    /**
     * 押金订单列表
     */
    public function index()
    {

        $model = new DepositOrderModel;
        $list = $model->getList($this->postData());
        //商户列表
        $supplierList = SupplierModel::getAll();
        return $this->renderSuccess('', compact('list', 'supplierList'));
    }

}
