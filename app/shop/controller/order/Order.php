<?php

namespace app\shop\controller\order;

use app\common\enum\order\OrderSourceEnum;
use app\shop\controller\Controller;
use app\shop\model\order\Order as OrderModel;
use app\common\enum\settings\DeliveryTypeEnum;
use app\shop\model\settings\Express as ExpressModel;
use app\shop\model\store\Clerk as ShopClerkModel;
use app\shop\model\supplier\Supplier as SupplierModel;

/**
 * 订单控制器
 */
class Order extends Controller
{
    /**
     * 订单列表
     */
    public function index($dataType = 'all')
    {
        // 订单列表
        $model = new OrderModel();
        $list = $model->getList($dataType, $this->postData());
        $order_count = [
            'order_count' => [
                'payment' => $model->getCount('payment', $this->postData()),
                'delivery' => $model->getCount('delivery', $this->postData()),
                'received' => $model->getCount('received', $this->postData()),
                'cancel' => $model->getCount('cancel', $this->postData()),
                'canceled' => $model->getCount('canceled', $this->postData()),
                'comment' => $model->getCount('comment', $this->postData()),
                'complete' => $model->getCount('complete', $this->postData()),
                'delete' => $model->getCount('delete', $this->postData()),
            ],];
        $ex_style = DeliveryTypeEnum::data();
        //商户列表
        $supplierList = SupplierModel::getAll();
        $sourceList = array_values(OrderSourceEnum::data());
        return $this->renderSuccess('', compact('list', 'ex_style', 'order_count', 'supplierList', 'sourceList'));
    }

    /**
     * 订单详情
     */
    public function detail($order_id)
    {
        // 订单详情
        $detail = OrderModel::detail($order_id);
        if (isset($detail['pay_time']) && $detail['pay_time']) {
            $detail['pay_time'] = date('Y-m-d H:i:s', $detail['pay_time']);
        }
        if (isset($detail['delivery_time']) && $detail['delivery_time']) {
            $detail['delivery_time'] = date('Y-m-d H:i:s', $detail['delivery_time']);
        }
        if ($detail['order_source'] == 70 && isset($detail['advance'])) {
            $detail['pay_price'] = round($detail['pay_price'] + $detail['advance']['pay_price'], 2);
            $detail['order_price'] = round($detail['order_price'] + $detail['advance']['pay_price'], 2);
        }
        if (isset($detail['receipt_time']) && $detail['receipt_time']) {
            $detail['receipt_time'] = date('Y-m-d H:i:s', $detail['receipt_time']);
        }
        // 物流公司列表
        $model = new ExpressModel();
        $expressList = $model->getAll();
        // 门店店员列表
        $shopClerkList = (new ShopClerkModel)->getAll($detail['shop_supplier_id']);
        return $this->renderSuccess('', compact('detail', 'expressList', 'shopClerkList'));
    }

    /**
     * 确认发货
     */
    public function delivery($order_id)
    {
        $model = OrderModel::detail($order_id);
        if ($model->delivery($this->postData('param'))) {
            return $this->renderSuccess('发货成功');
        }
        return $this->renderError('发货失败');
    }

    /**
     * 修改订单价格
     */
    public function updatePrice($order_id)
    {
        $model = OrderModel::detail($order_id);
        if ($model->updatePrice($this->postData('order'))) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     * 获取物流信息
     */
    public function express($order_id, $express_no, $express_id)
    {
        if (!$order_id || !$express_no || !$express_id) {
            return $this->renderError('参数错误');
        }
        $detail = ExpressModel::detail($express_id);
        // 订单信息
        $order = OrderModel::detail($order_id);
        if (!$order) {
            return $this->renderError('订单不存在');
        }
        if (!$detail) {
            return $this->renderError('没有物流信息');
        }
        // 获取物流信息
        $model = new ExpressModel();
        $express = $model->dynamic($detail['express_name'], $detail['express_code'], $express_no, $order['address']['phone']);
        if ($express === false) {
            return $this->renderError($model->getError());
        }
        return $this->renderSuccess('', compact('express'));
    }

    /**
     * 订单改地址
     */
    public function updateAddress($order_id)
    {
        // 订单信息
        $order = OrderModel::detail($order_id);
        if ($order['delivery_type'] == 10 && $order['delivery_status'] == 20) {
            return $this->renderError('订单已发货不允许修改');
        }
        // 获取物流信息
        $model = $order['address'];
        if (!$model->updateAddress($this->postData())) {
            return $this->renderError($model->getError() ?: '修改失败');
        }
        return $this->renderSuccess('修改成功');
    }

}