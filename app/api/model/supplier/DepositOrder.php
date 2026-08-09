<?php

namespace app\api\model\supplier;

use app\common\model\supplier\DepositOrder as DepositOrderModel;
use app\api\service\order\paysuccess\type\CashPaySuccessService;
use app\common\enum\order\OrderTypeEnum;
use app\common\enum\order\OrderPayTypeEnum;
use app\api\service\order\PaymentService;

/**
 * 押金订单模型类
 */
class DepositOrder extends DepositOrderModel
{

    /**
     * 创建订单
     */
    public function createOrder($user, $supplier)
    {
        if ($supplier['status'] != 20) {
            $this->error = '您已支付保证金';
            return false;
        }
        $price = $supplier['category']['deposit_money'];
        $orderInfo = $this->where('user_id', '=', $user['user_id'])
            ->where('pay_status', '=', 10)->find();
        if ($orderInfo) {
            $order_no = $this->orderNo();
            $this->where('order_id', $orderInfo['order_id'])->update(['order_no' => $order_no]);
            $this['order_id'] = $orderInfo['order_id'];
            $this['order_no'] = $order_no;
            $this['pay_price'] = $price;
        } else {
            // 获取订单数据
            $data = [
                'order_no' => $this->orderNo(),
                'user_id' => $user['user_id'],
                'pay_price' => $price,
                'shop_supplier_id' => $supplier['shop_supplier_id'],
                'app_id' => self::$app_id,
            ];
            $this->save($data);
        }
        return $this['order_id'];
    }

    /**
     * 待支付订单详情
     */
    public static function getPayDetail($orderNo, $pay_status)
    {
        $model = new static();
        $model = $model->where('order_no', '=', $orderNo);
        if ($pay_status > 0) {
            $model = $model->where('pay_status', '=', 10);
        }
        return $model->with(['user'])->find();
    }

    /**
     * 订单详情
     */
    public static function getUserOrderDetail($order_id, $user_id)
    {
        $model = new static();
        $order = $model->where(['order_id' => $order_id, 'user_id' => $user_id])->find();
        if (empty($order)) {
            throw new BaseException(['msg' => '订单不存在']);
        }
        return $order;
    }

    /**
     * 余额支付标记订单已支付
     */
    public function onPaymentByBalance($orderNo)
    {
        // 获取订单详情
        $PaySuccess = new CashPaySuccessService($orderNo);
        // 发起余额支付
        $status = $PaySuccess->onPaySuccess(OrderPayTypeEnum::BALANCE);
        if (!$status) {
            $this->error = $PaySuccess->getError();
        }
        return $status;
    }

    /**
     * 构建支付请求的参数
     */
    public static function onOrderPayment($user, $order, $payType, $pay_source)
    {
        //如果来源是h5,首次不处理，payH5再处理
//        if ($pay_source == 'h5') {
//            return [];
//        }
        if ($payType == OrderPayTypeEnum::WECHAT) {
            return self::onPaymentByWechat($user, $order, $pay_source);
        }
        if ($payType == OrderPayTypeEnum::ALIPAY) {
            return self::onPaymentByAlipay($user, $order, $pay_source);
        }
        return [];
    }

    /**
     * 构建微信支付请求
     */
    protected static function onPaymentByWechat($user, $order, $pay_source)
    {
        return PaymentService::wechat(
            $user,
            $order['order_no'],
            OrderTypeEnum::CASH,
            $pay_source,
            $order['online_money']
        );
    }

    /**
     * 构建支付宝请求
     */
    protected static function onPaymentByAlipay($user, $order, $pay_source)
    {
        return PaymentService::alipay(
            $user,
            $order['order_no'],
            OrderTypeEnum::CASH,
            $pay_source,
            $order['online_money']
        );
    }

    /**
     * 创建新订单
     */
    public function OrderPay($params, $order, $user)
    {
        $payType = $params['payType'];
        $payment = '';
        $online_money = 0;
        $order->save(['balance' => 0, 'online_money' => 0, 'order_no' => $this->orderNo()]);
        // 余额支付标记订单已支付
        if ($params['use_balance'] == 1) {
            if ($user['balance'] >= $order['pay_price']) {
                $payType = 10;
                $order->save(['balance' => $order['pay_price']]);
                $this->onPaymentByBalance($order['order_no']);
            } else {
                if ($payType <= 10) {
                    $this->error = '用户余额不足';
                    return false;
                }
                $online_money = round($order['pay_price'] - $user['balance'], 2);
                $order->save(['balance' => $user['balance'], 'online_money' => $online_money]);
            }
        } else {
            if ($payType <= 10) {
                $this->error = '请选择支付方式';
                return false;
            }
            $online_money = $order['pay_price'];
            $order->save(['online_money' => $order['pay_price']]);
        }
        $online_money > 0 && $payment = self::onOrderPayment($user, $order, $payType, $params['pay_source']);

        $result['order_id'] = $order['order_id'];
        $result['payType'] = $payType;
        $result['payment'] = $payment;
        $result['use_balance'] = $params['use_balance'];
        return $result;
    }

}
