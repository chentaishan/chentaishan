<?php

namespace app\supplier\controller\order;

use app\common\enum\order\OrderSourceEnum;
use app\common\enum\settings\DeliveryTypeEnum;
use app\supplier\model\settings\Setting as SettingModel;
use app\supplier\model\settings\DeliverySetting as DeliverySettingModel;
use app\supplier\model\settings\DeliveryTemplate as DeliveryTemplateModel;
use app\supplier\model\settings\ReturnAddress as ReturnAddressModel;
use app\supplier\model\store\Store as StoreModel;
use app\supplier\controller\Controller;
use app\supplier\model\order\Order as OrderModel;
use app\common\model\settings\Express as ExpressModel;
use app\supplier\model\store\Clerk as ClerkModel;

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
        $list = $model->getList($dataType, $this->postData(), $this->getSupplierId());
        $order_count = [
            'order_count' => [
                'payment' => $model->getCount('payment', $this->getSupplierId(), $this->postData()),
                'delivery' => $model->getCount('delivery', $this->getSupplierId(), $this->postData()),
                'received' => $model->getCount('received', $this->getSupplierId(), $this->postData()),
                'cancel' => $model->getCount('cancel', $this->getSupplierId(), $this->postData()),
                'canceled' => $model->getCount('canceled', $this->getSupplierId(), $this->postData()),
                'comment' => $model->getCount('comment', $this->getSupplierId(), $this->postData()),
                'complete' => $model->getCount('complete', $this->getSupplierId(), $this->postData()),
                'delete' => $model->getCount('delete', $this->getSupplierId(), $this->postData()),
            ],];
        // 自提门店列表
        $shop_list = StoreModel::getAllList($this->getSupplierId());
        $ex_style = DeliveryTypeEnum::data();
        $is_send_wx = SettingModel::getItem('store')['is_send_wx'];
        $sourceList = array_values(OrderSourceEnum::data());
        return $this->renderSuccess('', compact('list', 'order_count', 'shop_list', 'ex_style', 'is_send_wx', 'sourceList'));
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
        $shopClerkList = (new ClerkModel)->getClerk($detail['extract_store_id']);
        $template_list = DeliveryTemplateModel::getAll();
        $address_list = (new ReturnAddressModel)->getAll($this->getSupplierId());
        $label_list = DeliverySettingModel::getAll($this->getSupplierId());
        return $this->renderSuccess('', compact('detail', 'expressList', 'shopClerkList', 'template_list', 'address_list', 'label_list'));
    }

    /**
     * 确认发货
     */
    public function delivery($order_id)
    {
        $model = OrderModel::detail($order_id);
        if ($model->delivery($this->postData())) {
            return $this->renderSuccess('发货成功');
        }
        return $this->renderError($model->getError() ?: '发货失败');
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
        if ($order['delivery_type']['value'] == 10 && $order['delivery_status']['value'] == 20) {
            return $this->renderError('订单已发货不允许修改');
        }
        // 获取物流信息
        $model = $order['address'];
        if (!$model->updateAddress($this->postData())) {
            return $this->renderError($model->getError() ?: '修改失败');
        }
        return $this->renderSuccess('修改成功');
    }

    /**
     * 取消订单
     */
    public function orderCancel($order_no)
    {
        // 订单信息
        $model = OrderModel::detail(['order_no' => $order_no]);
        if ($model->orderCancel($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 虚拟商品发货
     */
    public function virtual($order_id)
    {
        // 订单信息
        $model = OrderModel::detail($order_id);
        if ($model->virtual($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 微信小程序发货
     */
    public function wxDelivery($order_id)
    {
        $model = OrderModel::detail($order_id);
        if ($model->wxDelivery()) {
            return $this->renderSuccess('发货成功');
        }
        return $this->renderError($model->getError() ?: '发货失败');
    }

    /**
     * 取消电子订单
     */
    public function labelCancel()
    {
        $model = new OrderModel();
        if ($model->labelCancel($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 电子订单复打
     */
    public function printRepeate()
    {
        $model = new OrderModel();
        if ($model->printRepeate($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }
}