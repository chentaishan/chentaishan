<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\api\model\order\Order as OrderModel;
use app\api\model\user\BalanceOrder as BalanceOrderModel;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\order\OrderTypeEnum;
use app\common\model\user\Sms as SmsModel;
use app\api\model\user\UserWeb as UserModel;
use app\api\model\plus\live\PlanOrder as PlanOrderModel;
use app\api\model\supplier\DepositOrder as DepositOrderModel;
use app\api\model\order\OrderAdvance as OrderAdvanceModel;

/**
 * h5 web用户管理
 */
class Userweb extends Controller
{

    /**
     * 用户自动登录,默认微信小程序
     */
    public function login()
    {
        $model = new UserModel;
        $user_id = $model->login($this->request->post());
        if ($user_id == 0) {
            return $this->renderError($model->getError() ?: '登录失败');
        }
        return $this->renderSuccess('', [
            'user_id' => $user_id,
            'token' => $model->getToken()
        ]);
    }

    /**
     * 短信登录
     */
    public function sendCode($mobile)
    {
        $model = new SmsModel();
        if ($model->send($mobile, 'login')) {
            return $this->renderSuccess();
        }
        return $this->renderError($model->getError() ?: '发送失败');
    }

    public function bindMobile()
    {
        $model = new UserModel;
        if ($model->bindMobile($this->getUser(), $this->request->post())) {
            return $this->renderSuccess('绑定成功');
        }
        return $this->renderError($model->getError() ?: '绑定失败');
    }

    public function payH5($order_id, $order_type = OrderTypeEnum::MASTER)
    {
        $user = $this->getUser();
        $model = null;
        $payment = null;
        $return_Url = '';
        if ($order_type == OrderTypeEnum::MASTER) {
            $params = $this->postData();
            $params['payType'] = OrderPayTypeEnum::WECHAT;
            // 构建支付请求
            $payInfo = (new OrderModel)->OrderPay($user, $params);
            $payment = $payInfo['payment'];
            $model = $payInfo;
            $return_Url = urlencode(base_url() . "h5/pages/order/myorder");
        } else if ($order_type == OrderTypeEnum::BALANCE) {
            // 订单详情
            $model = BalanceOrderModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = BalanceOrderModel::onOrderPayment($user, $model, OrderPayTypeEnum::WECHAT, 'payH5');
            $return_Url = urlencode(base_url() . "h5/pages/user/my-wallet/my-wallet");
        } else if ($order_type == OrderTypeEnum::PLAN) {
            // 订单详情
            $model = PlanOrderModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = PlanOrderModel::onOrderPayment($user, $model, OrderPayTypeEnum::WECHAT, 'payH5');
            $return_Url = urlencode(base_url() . "h5/pages/Live/live/live");
        } else if ($order_type == OrderTypeEnum::CASH) {
            // 订单详情
            $model = DepositOrderModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = DepositOrderModel::onOrderPayment($user, $model, OrderPayTypeEnum::WECHAT, 'payH5');
            $return_Url = urlencode(base_url() . "h5/pages/order/deposit-pay");
        } else if ($order_type == OrderTypeEnum::FRONT) {
            // 订单详情
            $model = OrderAdvanceModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = OrderAdvanceModel::onOrderPayment($user, $model, OrderPayTypeEnum::WECHAT, 'payH5');
            $return_Url = urlencode(base_url() . "h5/pages/order/myorder");
        }

        return $this->renderSuccess('', [
            'order' => $model,  // 订单详情
            'mweb_url' => $payment['mweb_url'],
            'return_Url' => $return_Url
        ]);
    }

    /**
     * h5下支付宝支付
     */
    public function alipayH5($order_id, $order_type = OrderTypeEnum::MASTER, $pay_source = 'payH5')
    {
        $user = $this->getUser();
        $payment = [];
        if ($order_type == OrderTypeEnum::MASTER) {
            $params = $this->postData();
            $params['payType'] = OrderPayTypeEnum::ALIPAY;
            // 构建支付请求
            $payInfo = (new OrderModel)->OrderPay($user, $params);
            $payment = $payInfo['payment'];
        } else if ($order_type == OrderTypeEnum::BALANCE) {
            // 订单详情
            $model = BalanceOrderModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = BalanceOrderModel::onOrderPayment($user, $model, OrderPayTypeEnum::ALIPAY, $pay_source);
        } else if ($order_type == OrderTypeEnum::PLAN) {
            // 订单详情
            $model = PlanOrderModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = PlanOrderModel::onOrderPayment($user, $model, OrderPayTypeEnum::ALIPAY, $pay_source);
        } else if ($order_type == OrderTypeEnum::CASH) {
            // 订单详情
            $model = DepositOrderModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = DepositOrderModel::onOrderPayment($user, $model, OrderPayTypeEnum::ALIPAY, $pay_source);
        } else if ($order_type == OrderTypeEnum::FRONT) {
            // 订单详情
            $model = OrderAdvanceModel::getUserOrderDetail($order_id, $user['user_id']);
            // 构建支付请求
            $payment = OrderAdvanceModel::onOrderPayment($user, $model, OrderPayTypeEnum::ALIPAY, $pay_source);
        }
        return $this->renderSuccess('', [
            'payment' => $payment,
        ]);
    }
}
