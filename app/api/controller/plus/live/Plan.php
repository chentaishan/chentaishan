<?php

namespace app\api\controller\plus\live;

use app\api\controller\Controller;
use app\api\model\plus\live\Plan as PlanModel;
use app\api\model\plus\live\PlanOrder as PlanOrderModel;
use app\common\model\app\App as AppModel;
use app\common\enum\order\OrderTypeEnum;

/**
 * 充值套餐控制器
 */
class Plan extends Controller
{
    /**
     * 套餐列表
     */
    public function lists()
    {
        $model = new PlanModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 充值套餐
     */
    public function submit($plan_id)
    {
        // 用户信息
        $user = $this->getUser();
        // 生成等级订单
        $model = new PlanOrderModel;
        $order_id = $model->createOrder($user, $plan_id);
        if (!$order_id) {
            return $this->renderError($model->getError() ?: '创建订单失败');
        }
        // 返回结算信息
        return $this->renderSuccess('', [
            'order_id' => $order_id,   // 订单id
        ]);
    }

    /**
     * 立即支付
     */
    public function pay($order_id)
    {
        // 用户信息
        $user = $this->getUser();
        // 获取订单详情
        $model = PlanOrderModel::getUserOrderDetail($order_id, $user['user_id']);
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
        if ($model['pay_status']['value'] != 10) {
            return $this->renderError($model->getError() ?: '订单已支付');
        }
        // 构建微信支付请求
        $PlanOrderModel = new PlanOrderModel;
        $payInfo = (new PlanOrderModel)->OrderPay($params, $model, $user);
        if (!$payInfo) {
            return $this->renderError($PlanOrderModel->getError() ?: '订单支付失败');
        }
        // 支付状态提醒
        return $this->renderSuccess('', [
            'order_id' => $order_id,   // 订单id
            'pay_type' => $payInfo['payType'],  // 支付方式
            'payment' => $payInfo['payment'],   // 微信支付参数
            'order_type' => OrderTypeEnum::PLAN, //订单类型
            'use_balance' => $payInfo['use_balance']// 是否使用余额
        ]);
    }
}