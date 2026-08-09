<?php

namespace app\api\model\order;

use app\api\service\order\PaymentService;
use app\api\service\order\paysuccess\type\FrontPaySuccessService;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\order\OrderTypeEnum;
use app\common\exception\BaseException;
use app\common\model\order\OrderAdvance as OrderAdvanceModel;
use app\api\model\order\Order as OrderModel;
use app\common\model\plus\advance\AdvanceSku as ProductSkuModel;
use app\common\enum\product\DeductStockTypeEnum;
use app\common\service\product\factory\ProductFactory;
use app\common\enum\order\OrderPayStatusEnum;
use app\common\service\order\OrderRefundService;

/**
 * 预售定金模型
 */
class OrderAdvance extends OrderAdvanceModel
{

    /**
     * 订单支付事件
     * 返回0，失败 1，需要继续支付 2，无需继续支付，余额支付成功
     */
    public function onPay()
    {
        if ($this['order_status'] != 10 || $this['pay_status']['value'] != 10) {
            $this->error = '很抱歉，当前订单不合法，无法支付';
            return false;
        }
        if ($this['end_time'] < time()) {
            $this->error = "很抱歉，预售商品时间已结束";
            return false;
        }
        if ($this['pay_end_time'] < time()) {
            $this->error = "很抱歉，超过支付时间";
            return false;
        }
        $order = OrderModel::detail($this['order_id']);
        $productList = $order['product'];
        foreach ($productList as $product) {
            // 预售商品sku信息
            $advanceProductSku = ProductSkuModel::detail($product['sku_source_id'], ['product']);
            $advanceProduct = $advanceProductSku['product'];
            // sku是否存在
            if (empty($advanceProductSku)) {
                $this->error = "很抱歉，商品 [{$product['product_name']}] sku已不存在，请重新下单";
                return false;
            }
            // 判断商品是否下架
            if (empty($advanceProduct)) {
                $this->error = "很抱歉，商品 [{$product['product_name']}] 不存在或已删除";
                return false;
            }
            // 付款减库存
            if ($product['deduct_stock_type'] == DeductStockTypeEnum::PAYMENT && $product['total_num'] > $advanceProductSku['advance_stock']) {
                $this->error = "很抱歉，商品 [{$product['product_name']}] 库存不足";
                return false;
            }
        }
        return true;
    }

    /**
     * 余额支付标记订单已支付
     */
    public function onPaymentByBalance($orderNo)
    {
        // 获取订单详情
        $PaySuccess = new FrontPaySuccessService($orderNo);
        // 发起余额支付
        return $PaySuccess->onPaySuccess(OrderPayTypeEnum::BALANCE);
    }

    /**
     * 待支付订单详情
     */
    public static function getPayDetail($orderNo, $pay_status)
    {
        $model = new static();
        $model = $model->where('trade_no', '=', $orderNo);
        if ($pay_status > 0) {
            $model = $model->where('pay_status', '=', 10);
        }
        return $model->with(['user', 'advance', 'orderM'])->find();
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
            $order['trade_no'],
            OrderTypeEnum::FRONT,
            $pay_source,
            $order['online_money'],
            0
        );
    }

    /**
     * 构建支付宝请求
     */
    protected static function onPaymentByAlipay($user, $order, $pay_source)
    {
        return PaymentService::alipay(
            $user,
            $order['trade_no'],
            OrderTypeEnum::FRONT,
            $pay_source,
            $order['online_money'],
            0
        );
    }

    /**
     * 订单详情
     */
    public static function getUserOrderDetail($order_id, $user_id)
    {
        $model = new static();
        $order = $model->with(['advance', 'orderM.product'])->where(['order_advance_id' => $order_id, 'user_id' => $user_id])->find();
        if (empty($order)) {
            throw new BaseException(['msg' => '订单不存在']);
        }
        return $order;
    }

    /**
     * 创建新订单
     */
    public function OrderPay($params, $order, $user)
    {
        $payType = $params['payType'];
        $payment = '';
        $online_money = 0;
        $order->save(['balance' => 0, 'online_money' => 0, 'trade_no' => $this->orderNo()]);
        // 余额支付标记订单已支付
        if ($params['use_balance'] == 1) {
            if ($user['balance'] >= $order['pay_price']) {
                $payType = 10;
                $order->save(['balance' => $order['pay_price']]);
                $this->onPaymentByBalance($order['trade_no']);
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

    /**
     * 获取活动订单
     * 已付款，未取消
     */
    public static function getPlusOrderNum($user_id, $product_id)
    {
        $model = new static();
        return $model->where('user_id', '=', $user_id)
            ->where('advance_product_id', '=', $product_id)
            ->where('pay_status', '=', 20)
            ->where('order_status', '<>', 20)
            ->count();
    }

    /**
     * 取消订单
     */
    public function cancel($user)
    {
        if ($this['money_return'] != 1) {
            $this->error = '订单不允许取消';
            return false;
        }
        // 订单取消事件
        return $this->transaction(function () use ($user) {
            // 订单是否已支付
            $isPay = $this['pay_status']['value'] == OrderPayStatusEnum::SUCCESS;
            //主商品退回库存
            ProductFactory::getFactory($this['orderM']['order_source'])->backProductStock($this['orderM']['product'], $isPay);
            $data['order_status'] = 20;
            // 未付款的订单
            if ($isPay == true) {
                // 执行退款操作
                (new OrderRefundService)->execute($this, 0);
                $data['refund_money'] = $this['pay_price'];
                $data['is_refund'] = 1;
            }
            // 更新订单状态
            $this['orderM']->save(['order_status' => 20]);
            return $this->save($data);
        });
    }

    /**
     * 设置错误信息
     */
    protected function setError($error)
    {
        empty($this->error) && $this->error = $error;
    }

    /**
     * 是否存在错误
     */
    public function hasError()
    {
        return !empty($this->error);
    }
}