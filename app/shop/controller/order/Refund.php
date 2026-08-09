<?php

namespace app\shop\controller\order;

use app\shop\controller\Controller;
use app\shop\model\order\Order as OrderModel;
use app\shop\model\order\OrderRefund as OrderRefundModel;
use app\shop\model\settings\ReturnAddress as ReturnAddressModel;
use app\shop\model\supplier\Supplier as SupplierModel;

/**
 * 售后管理
 */
class Refund extends Controller
{
    /**
     * 售后列表
     */
    public function index()
    {
        $model = new OrderRefundModel;
        $params = $this->postData();
        //列表数据
        $list = $model->getList($params);
        //重要数字
        $num_arr = $model->groupCount($params);
        $arr = [];
        foreach ($num_arr as $key => $val) {
            $k = $val['status']['value'];
            $arr[$k] = $val;
        }
        //商户列表
        $supplierList = SupplierModel::getAll();
        return $this->renderSuccess('', compact('list', 'arr', 'supplierList'));
    }

    /**
     * 售后单详情
     */
    public function detail($order_refund_id)
    {
        // 售后单详情
        $detail = OrderRefundModel::detail($order_refund_id);
        if (isset($detail['send_time']) && $detail['send_time'] > 0) {
            $detail['send_time'] = date('Y-m-d H:i:s', $detail['send_time']);
        }
        // 订单详情
        $order = OrderModel::detail($detail['order_id']);
        $detail['orderproduct']['max_refund_money'] = $detail['orderproduct']['total_pay_price'];
        if ($order['order_source'] == 70) {
            $detail['orderproduct']['total_pay_price'] = round($detail['orderproduct']['total_pay_price'] + $order['advance']['pay_price'], 2);
            if ($order['advance']['money_return'] == 1) {
                $detail['orderproduct']['max_refund_money'] = round($detail['orderproduct']['max_refund_money'] + $order['advance']['pay_price'], 2);
            }
        }
        // 退货地址
        $address = (new ReturnAddressModel)->getAll($detail['shop_supplier_id']);
        return $this->renderSuccess('', compact('detail', 'order', 'address'));
    }

    /**
     * 商家审核
     */
    public function audit($order_refund_id)
    {
        $model = OrderRefundModel::detail($order_refund_id);
        if ($model->audit($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 确认收货并退款
     */
    public function receipt($order_refund_id)
    {
        if (!$this->request->isPost()) {
            return false;
        }
        $model = OrderRefundModel::detail($order_refund_id);
        if ($model->receipt($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

}