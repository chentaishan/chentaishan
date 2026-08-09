<?php

namespace app\api\controller\supplier;

use app\api\controller\Controller;
use app\api\model\order\Order as OrderModel;
use app\api\model\settings\Setting as SettingModel;
use app\api\service\pay\PayService;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\exception\BaseException;
use app\common\service\qrcode\ExtractService;
use app\common\model\supplier\Service as ServiceModel;
use app\common\model\supplier\User as SupplierUserModel;
use app\api\model\settings\Express as ExpressModel;

/**
 * 我的订单
 */
class Order extends Controller
{
    // user
    private $user;
    private $supplierUser;

    /**
     * 构造方法
     */
    public function initialize()
    {
        parent::initialize();
        $this->user = $this->getUser();   // 用户信息
        $this->supplierUser = $this->getSupplierUser($this->user);
    }

    /**
     * 我的订单列表
     */
    public function index($dataType)
    {
        $data = $this->postData();
        $model = new OrderModel;
        $data['shop_supplier_id'] = $this->supplierUser['shop_supplier_id'];
        $list = $model->getList($this->user['user_id'], $dataType, $data);
        $show_alipay = PayService::isAlipayOpen($data['pay_source'], $this->user['app_id']);
        return $this->renderSuccess('', compact('list', 'show_alipay'));
    }

    /**
     * 订单详情信息
     */
    public function detail($order_id, $pay_source = '')
    {
        $shop_supplier_id = $this->supplierUser['shop_supplier_id'];
        // 订单详情
        $model = OrderModel::getSupplierOrderDetail($order_id, $shop_supplier_id);
        // 剩余支付时间
        if ($model['pay_status']['value'] == 10 && $model['order_status']['value'] != 20 && $model['pay_end_time'] != 0) {
            $model['pay_end_time'] = $this->formatPayEndTime($model['pay_end_time'] - time());
        } else {
            $model['pay_end_time'] = '';
        }
        // 物流公司列表
        $ExpressModel = new ExpressModel();
        $expressList = $ExpressModel->getAll();
        // 该订单是否允许申请售后
        $model['isAllowRefund'] = $model->isAllowRefund();
        $model['supplier']['supplier_user_id'] = (new SupplierUserModel())->where('shop_supplier_id', '=', $model['shop_supplier_id'])->value('supplier_user_id');
        return $this->renderSuccess('', [
            'order' => $model,  // 订单详情
            'setting' => [
                // 积分名称
                'points_name' => SettingModel::getPointsName(),
                //是否开启客服
                'service_open' => SettingModel::getSysConfig()['service_open'],
                //店铺客服信息
                'mp_service' => ServiceModel::detail($model['shop_supplier_id']),
            ],
            'show_alipay' => PayService::isAlipayOpen($pay_source, $model['app_id']),
            // 物流公司列表
            'expressList' => $expressList
        ]);
    }

    /**
     * 支付成功详情信息
     */
    public function paySuccess($order_id)
    {
        $order_arr = explode(',', $order_id);
        $order = [
            'pay_price' => 0,
            'points_bonus' => 0
        ];
        foreach ($order_arr as $id) {
            $model = OrderModel::getUserOrderDetail($id, $this->user['user_id']);
            $order['pay_price'] += $model['pay_price'];
            $order['points_bonus'] += $model['points_bonus'];
        }
        return $this->renderSuccess('', compact('order'));
    }

    /**
     * 获取物流信息
     */
    public function express($order_id)
    {
        // 订单信息
        $order = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
        if (!$order['express_no']) {
            return $this->renderError('没有物流信息');
        }
        // 获取物流信息
        $model = $order['express'];
        $express = $model->dynamic($model['express_name'], $model['express_code'], $order['express_no'], $order['address']['phone']);
        if ($express === false) {
            return $this->renderError($model->getError());
        }
        return $this->renderSuccess('', compact('express'));
    }

    /**
     * 取消订单
     */
    public function cancel($order_id)
    {
        $model = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
        if ($model->cancel($this->user)) {
            return $this->renderSuccess('订单取消成功');
        }
        return $this->renderError($model->getError() ?: '订单取消失败');
    }

    /**
     * 确认收货
     */
    public function receipt($order_id)
    {
        $model = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
        if ($model->receipt()) {
            return $this->renderSuccess('收货成功');
        }
        return $this->renderError($model->getError() ?: '收货失败');
    }

    /**
     * 立即支付
     */
    public function pay($order_id, $payType = OrderPayTypeEnum::WECHAT, $pay_source = 'wx')
    {
        // 获取订单详情
        $model = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
        // 订单支付事件
        if (!$model->onPay($payType)) {
            return $this->renderError($model->getError() ?: '订单支付失败');
        }
        // 构建微信支付请求
        $payment = $model->onOrderPayment($this->user, [$model], $payType, $pay_source);
        // 支付状态提醒
        return $this->renderSuccess('', [
            'order_id' => $model['order_id'],   // 订单id
            'pay_type' => $payType,             // 支付方式
            'payment' => $payment               // 微信支付参数
        ]);
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
     * 审核：用户取消订单
     */
    public function confirmCancel($order_id)
    {
        $model = OrderModel::detail($order_id);
        if ($model->confirmCancel($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError('操作失败');
    }

    /**
     * 确认发货
     */
    public function delivery($order_id)
    {
        $model = OrderModel::detail($order_id);
        $data = $this->postData();
        $data['is_single'] = 1;
        $data['is_label'] = 0;
        if ($model->delivery($data)) {
            return $this->renderSuccess('发货成功');
        }
        return $this->renderError('发货失败');
    }

    /**
     * 获取订单核销二维码
     */
    public function qrcode($order_id, $source)
    {
        // 订单详情
        $order = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
        // 判断是否为待核销订单
        if (!$order->checkExtractOrder($order)) {
            return $this->renderError($order->getError());
        }
        $Qrcode = new ExtractService(
            $this->app_id,
            $this->user,
            $order_id,
            $source,
            $order['order_no']
        );
        return $this->renderSuccess('', [
            'qrcode' => $Qrcode->getImage(),
        ]);
    }

    private function formatPayEndTime($leftTime)
    {
        if ($leftTime <= 0) {
            return '';
        }

        $str = '';
        $day = floor($leftTime / 86400);
        $hour = floor(($leftTime - $day * 86400) / 3600);
        $min = floor((($leftTime - $day * 86400) - $hour * 3600) / 60);

        if ($day > 0) $str .= $day . '天';
        if ($hour > 0) $str .= $hour . '小时';
        if ($min > 0) $str .= $min . '分钟';
        return $str;
    }
}