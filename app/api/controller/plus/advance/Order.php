<?php

namespace app\api\controller\plus\advance;

use app\api\model\plus\advance\Product as ProductModel;
use app\api\service\order\settled\FrontOrderSettledService;
use app\api\controller\Controller;
use app\api\model\settings\Message as MessageModel;
use app\api\model\order\OrderAdvance as OrderAdvanceModel;
use app\common\enum\order\OrderTypeEnum;
use app\common\model\app\App as AppModel;

/**
 * 预售订单
 */
class Order extends Controller
{
    /**
     * 支付定金订单
     */
    public function frontBuy()
    {
        // 预售订单：获取订单商品列表
        $params = json_decode($this->postData()['params'], true);
        $productList = ProductModel::getAdvanceProduct($params);
        $user = $this->getUser();
        // 实例化订单service
        $orderService = new FrontOrderSettledService($user, $productList, $params);
        // 获取订单信息
        $orderInfo = $orderService->paySettlement();

        if ($this->request->isGet()) {
            // 如果来源是小程序, 则获取小程序订阅消息id.获取支付成功,发货通知.
            $template_arr = MessageModel::getMessageByNameArr($params['pay_source'], ['order_pay_user', 'order_delivery_user']);
            return $this->renderSuccess('', compact('orderInfo', 'template_arr'));
        }
        // 订单结算提交
        if ($orderService->hasError()) {
            return $this->renderError($orderService->getError());
        }
        // 创建订单
        $order_id = $orderService->createPayOrder($orderInfo);
        if (!$order_id) {
            return $this->renderError($orderService->getError() ?: '订单创建失败');
        }
        // 返回结算信息
        return $this->renderSuccess('', [
            'order_id' => $order_id,   // 订单id
            'order_type' => OrderTypeEnum::FRONT, //订单类型
        ]);
    }

    /**
     * 立即支付定金
     */
    public function pay($order_id)
    {
        $user = $this->getUser();
        // 获取订单详情
        $model = OrderAdvanceModel::getUserOrderDetail($order_id, $user['user_id']);
        $params = $this->postData();
        if ($this->request->isGet()) {
            // 开启的支付类型
            $payTypes = AppModel::getPayType($model['app_id'], $params['pay_source'], $user);
            // 支付金额
            $payPrice = $model['pay_price'];
            $balance = $user['balance'];
            return $this->renderSuccess('', compact('payTypes', 'payPrice', 'balance'));
        }
        // 订单支付事件
        if (!$model->onPay()) {
            return $this->renderError($model->getError() ?: '订单支付失败');
        }
        $OrderModel = new OrderAdvanceModel;
        // 构建微信支付请求
        $payInfo = $OrderModel->OrderPay($params, $model, $user);
        if (!$payInfo) {
            return $this->renderError($OrderModel->getError() ?: '订单支付失败');
        }
        // 支付状态提醒
        return $this->renderSuccess('', [
            'order_advance_id' => $order_id,   // 定金订单id
            'order_id' => $model['order_id'],   // 主订单id
            'pay_type' => $payInfo['payType'],  // 支付方式
            'payment' => $payInfo['payment'],   // 微信支付参数
            'order_type' => OrderTypeEnum::FRONT, //订单类型
            'use_balance' => $payInfo['use_balance'],// 是否使用余额
            'return_Url' => $params['pay_source'] == 'h5' ? urlencode(base_url() . "h5/pages/order/myorder") : '', //h5支付跳转地址
        ]);
    }

    /**
     * 取消定金订单
     */
    public function cancelFront($order_id)
    {
        $user = $this->getUser();
        $model = OrderAdvanceModel::getUserOrderDetail($order_id, $user['user_id']);
        if ($model->cancel($user)) {
            return $this->renderSuccess('订单取消成功');
        }
        return $this->renderError($model->getError() ?: '订单取消失败');
    }
}