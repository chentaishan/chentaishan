<?php

namespace app\api\model\order;

use app\api\controller\product\Product;
use app\api\model\plus\buy\BuyActivity as BuyActivityModel;
use app\api\model\product\Product as ProductModel;
use app\api\service\order\paysuccess\type\MasterPaySuccessService;
use app\api\service\order\PaymentService;
use app\api\model\settings\Setting as SettingModel;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\order\OrderSourceEnum;
use app\common\enum\order\OrderTypeEnum;
use app\common\enum\order\OrderPayStatusEnum;
use app\common\enum\order\OrderStatusEnum;
use app\common\exception\BaseException;
use app\common\model\order\CloudRatio;
use app\common\model\user\User;
use app\common\service\order\OrderCompleteService;
use app\common\enum\settings\DeliveryTypeEnum;
use app\common\library\helper;
use app\common\model\order\Order as OrderModel;
use app\api\service\order\checkpay\CheckPayFactory;
use app\common\service\product\factory\ProductFactory;
use app\common\service\activity\WzNormalZoneProductService;
use app\common\model\plus\coupon\UserCoupon as UserCouponModel;
use app\common\model\order\OrderTrade as OrderTradeModel;
use app\common\service\order\OrderRefundService;
use app\common\service\greenpoints\GreenPointsService;

/**
 * 普通订单模型
 */
class Order extends OrderModel
{
    /**
     * 隐藏字段
     * @var array
     */
    protected $hidden = [
        'update_time'
    ];

    /**
     * 订单支付事件
     */
    public function onPay($params, $user)
    {
        $orderID = explode(',', $params['order_id']);
        if (count($orderID) == 1) {
            $order = self::getUserOrderDetail($params['order_id'], $user['user_id']);
            // 判断订单状态
            $checkPay = CheckPayFactory::getFactory($order['order_source']);

            if (!$checkPay->checkOrderStatus($order)) {
                $this->error = $checkPay->getError();
                return false;
            }
        }
        return true;
    }

    /**
     * 用户中心订单列表
     */
    public function getList($user_id, $type, $params)
    {
        // 筛选条件
        $filter = [];
        $model = (new self)->alias('order');
        // 订单数据类型
        switch ($type) {
            case 'all':
                break;
            case 'payment';
                $filter['pay_status'] = OrderPayStatusEnum::PENDING;
                $filter['order_status'] = 10;
                break;
            case 'delivery';
                $filter['pay_status'] = OrderPayStatusEnum::SUCCESS;
                $filter['order_status'] = 10;
                $model = $model->where('delivery_status', '<>', 20);
                // 已全额退款的订单不再出现在待发货列表
                $model = $model->whereColumn('order.refund_money', '<', 'order.pay_price');
                break;
            case 'received';
                $filter['pay_status'] = OrderPayStatusEnum::SUCCESS;
                $filter['delivery_status'] = 20;
                $filter['receipt_status'] = 10;
                $filter['order_status'] = 10;
                // 已全额退款的订单不再出现在待收货(已发货)列表
                $model = $model->whereColumn('order.refund_money', '<', 'order.pay_price');
                break;
            case 'comment';
                $filter['order.is_comment'] = 0;
                $filter['order_status'] = 30;
                $model = $model->where('order.zone_type', '<>', WzNormalZoneProductService::ZONE_FIRST);
                break;
        }
        if (isset($params['shop_supplier_id']) && $params['shop_supplier_id']) {
            $model = $model->where('order.shop_supplier_id', '=', $params['shop_supplier_id']);
        } else {
            // 用户查询
            $model = $model->where('order.user_id', '=', $user_id);
        }
        if (isset($params['search']) && $params['search']) {
            $model = $model->where('order_no|product_name', 'like', '%' . $params['search'] . '%');
        }
        $list = $model->with(['product.image', 'supplier', 'advance'])
            ->join('order_product op', 'op.order_id=order.order_id')
            ->where($filter)
            ->where('order.is_delete', '=', 0)
            ->order(['order.create_time' => 'desc'])
            ->group('order.order_id')
            ->field('order.*')
            ->paginate($params);
        foreach ($list as &$item) {
            if ($item['pay_status']['value'] == 10 && $item['order_status']['value'] != 20 && $item['pay_end_time'] != 0) {
                $item['pay_end_time_format'] = $this->formatPayEndTime($item['pay_end_time'] - time());
            } else {
                $item['pay_end_time_format'] = '';
            }
            //查询是否多个商户订单
            $item['orderSupplierCount'] = $this->getOrderSupplierCount($item['order_id']);
            $total_num = 0;
            foreach ($item['product'] as $product) {
                $total_num += $product['total_num'];
            }
            $item['productNum'] = $total_num;
        }
        return $list;
    }

    /**
     * 用户中心拼团订单列表
     */
    public function getAssembleList($user_id, $params)
    {
        // 筛选条件
        $filter = [];
        // 订单数据类型
        switch ($params['type']) {
            case '10'://所有订单
                break;
            case '20';//待支付订单
                $filter['pay_status'] = OrderPayStatusEnum::PENDING;
                $filter['order_status'] = 10;
                break;
            case '30';//拼团中
                $filter['pay_status'] = OrderPayStatusEnum::SUCCESS;
                $filter['assemble_status'] = 10;
                $filter['order_status'] = 10;
                break;
            case '40';//拼团成功
                $filter['pay_status'] = OrderPayStatusEnum::SUCCESS;
                $filter['assemble_status'] = 20;
                break;
            case '50';//拼团失败
                $filter['pay_status'] = OrderPayStatusEnum::SUCCESS;
                $filter['assemble_status'] = 30;
                break;
        }
        $list = $this->with(['product.image', 'supplier', 'advance'])
            ->where('user_id', '=', $user_id)
            ->where('order_source', '=', OrderSourceEnum::ASSEMBLE)
            ->where($filter)
            ->where('is_delete', '=', 0)
            ->order(['create_time' => 'desc'])
            ->paginate($params);
        foreach ($list as &$item) {
            if ($item['pay_status']['value'] == 10 && $item['order_status']['value'] != 20 && $item['pay_end_time'] != 0) {
                $item['pay_end_time_format'] = $this->formatPayEndTime($item['pay_end_time'] - time());
            } else {
                $item['pay_end_time_format'] = '';
            }
        }
        return $list;
    }

    /**
     * 确认收货
     */
    public function receipt()
    {
        // 验证订单是否合法
        // 条件1: 订单必须已发货
        // 条件2: 订单必须未收货
        if ($this['delivery_status']['value'] != 20 || $this['receipt_status']['value'] != 10) {
            $this->error = '该订单不合法';
            return false;
        }
        if (OrderRefund::hasActiveByOrderId((int)$this['order_id'])) {
            $this->error = '订单售后中，暂不能确认收货';
            return false;
        }
        return $this->transaction(function () {
            // 更新订单状态
            $status = $this->save([
                'receipt_status' => 20,
                'receipt_time' => time(),
                'order_status' => 30
            ]);
            // 执行订单完成后的操作
            $OrderCompleteService = new OrderCompleteService(OrderTypeEnum::MASTER);
            $OrderCompleteService->complete([$this], static::$app_id);
            return $status;
        });
    }

    /**
     * 立即购买：获取订单商品列表
     */
    public static function getOrderProductListByNow($params, $user = null)
    {
        // 商品详情
        $product = ProductModel::detail($params['product_id']);
        // 商品sku信息
        $product['product_sku'] = ProductModel::getProductSku($product, $params['product_sku_id']);
        if (!$product['product_sku']) {
            throw new BaseException(['msg' => '很抱歉，商品规格不存在']);
        }
        if ($user) {
            $agentStockErr = WzNormalZoneProductService::validateAgentStockPurchase($user, $product);
            if ($agentStockErr !== null) {
                throw new BaseException(['msg' => $agentStockErr]);
            }
        }
        // 商品列表
        $productList = [$product->hidden(['category', 'content', 'image', 'sku'])];
        foreach ($productList as &$item) {
            $itemSku = $item['product_sku'] ?? [];
            if (is_object($itemSku) && method_exists($itemSku, 'toArray')) {
                $itemSku = $itemSku->toArray();
            }
            // 商品单价
            $item['product_price'] = $itemSku['product_price'] ?? 0;
            // 商品购买数量
            $item['total_num'] = $params['product_num'];
            $item['spec_sku_id'] = $itemSku['spec_sku_id'] ?? '';
            if (is_object($item) && method_exists($item, 'set')) {
                $item->set('product_sku', $itemSku);
            } else {
                $item['product_sku'] = $itemSku;
            }
            // 甲丽华威分区价（与购物车、结算台一致）
            if ($user) {
                WzNormalZoneProductService::applyBuyerPriceToProduct($item, $user);
            }
            // 商品购买总金额（分区价已在 apply 内按数量计算）
            if (!$user || !WzNormalZoneProductService::usesTierPricing((int)($item['zone_type'] ?? 0))) {
                $item['total_price'] = helper::bcmul($item['product_price'], $params['product_num']);
            }
        }
        unset($item);
        $supplierData[] = [
            'shop_supplier_id' => $product['shop_supplier_id'],
            'supplier' => $product['supplier'],
            'productList' => $productList,
            'buyProduct' => (new BuyActivityModel)->getDetail($productList, $product['shop_supplier_id'])
        ];
        unset($product['supplier']);
        return $supplierData;
    }

    /**
     * 获取订单总数（筛选条件与 getList 保持一致）
     */
    public function getCount($user, $type = 'all')
    {
        if ($user === false) {
            return false;
        }
        $filter = [];
        $model = $this->where('user_id', '=', $user['user_id'])
            ->where('is_delete', '=', 0);

        switch ($type) {
            case 'all':
                break;
            case 'payment':
                $filter['pay_status'] = OrderPayStatusEnum::PENDING;
                $filter['order_status'] = 10;
                break;
            case 'delivery':
                $filter['pay_status'] = OrderPayStatusEnum::SUCCESS;
                $filter['order_status'] = 10;
                $model = $model->where('delivery_status', '<>', 20)
                    ->whereColumn('refund_money', '<', 'pay_price');
                break;
            case 'received':
                $filter['pay_status'] = OrderPayStatusEnum::SUCCESS;
                $filter['delivery_status'] = 20;
                $filter['receipt_status'] = 10;
                $filter['order_status'] = 10;
                $model = $model->whereColumn('refund_money', '<', 'pay_price');
                break;
            case 'comment':
                $filter['order_status'] = 30;
                $filter['is_comment'] = 0;
                $model = $model->where('zone_type', '<>', WzNormalZoneProductService::ZONE_FIRST);
                break;
        }

        return (int)$model->where($filter)->count();
    }

    /**
     * 取消订单
     */
    public function cancel($user)
    {
        if ($this['delivery_status']['value'] == 20) {
            $this->error = '已发货订单不可取消';
            return false;
        }
        //进行中的拼团订单不能取消
        if ($this['order_source'] == OrderSourceEnum::ASSEMBLE) {
            if ($this['assemble_status'] == 10) {
                $this->error = '订单正在拼团，到期后如果订单未拼团成功将自动退款';
                return false;
            }
        }
        // 订单取消事件
        return $this->transaction(function () use ($user) {
            // 订单是否已支付
            $isPay = $this['pay_status']['value'] == OrderPayStatusEnum::SUCCESS;
            // 未付款的订单
            if ($isPay == false) {
                //主商品退回库存
                ProductFactory::getFactory($this['order_source'])->backProductStock($this['product'], $isPay);
                // 回退用户优惠券
                $this->backCoupon();
                // 回退用户积分
                $describe = "订单取消：{$this['order_no']}";
                $this['points_num'] > 0 && $user->setIncPoints($this['points_num'], $describe);
                // 回退消费券
                (new \app\common\service\greenpoints\GreenPointsService())->refundVoucherForCancelledOrder($this->toArray());
                //判断是否为预售订单
                if ($this['order_source'] == OrderSourceEnum::ADVANCE) {
                    if ($this['advance']['money_return'] == 1) {//预售订单退定金
                        if ((new OrderRefundService)->execute($this['advance'], 0)) {
                            // 更新订单状态
                            $this['advance']->save([
                                'is_refund' => 1,
                                'refund_money' => $this['advance']['pay_price'],
                            ]);
                        }
                    }
                    $this['advance']->save(['order_status' => 20]);
                }
            }
            // 更新订单状态
            return $this->save(['order_status' => $isPay ? OrderStatusEnum::APPLY_CANCEL : OrderStatusEnum::CANCELLED]);
        });
    }

    /**
     * 订单详情
     */
    public static function getUserOrderDetail($order_id, $user_id)
    {
        $model = new static();
        $order = $model->where(['order_id' => $order_id, 'user_id' => $user_id])->with(['product' => ['image', 'refund'], 'address', 'express', 'extractStore', 'supplier', 'advance'])->find();
        if (empty($order)) {
            throw new BaseException(['msg' => '订单不存在']);
        }
        return $order;
    }

    /**
     * 订单详情
     */
    public static function getOrderDetail($order_id, $user_id)
    {
        $model = new static();
        $order = $model->where(['order_id' => $order_id, 'user_id' => $user_id])->with(['product' => ['image', 'refund'], 'address', 'express', 'extractStore', 'supplier', 'advance'])->find();
        if (empty($order)) {
            throw new BaseException(['msg' => '订单不存在']);
        }
        foreach ($order['product'] as &$item) {
            $refund = (new OrderRefund())->allowRefund($order_id, $item['order_product_id']);
            $item['allowRefund'] = $refund ? true : false;
        }
        return $order;
    }

    /**
     * 供应商查看订单详情
     */
    public static function getSupplierOrderDetail($order_id, $shop_supplier_id)
    {
        $model = new static();
        $order = $model->where(['order_id' => $order_id, 'shop_supplier_id' => $shop_supplier_id])->with(['product' => ['image', 'refund'], 'address', 'express', 'extractStore', 'supplier', 'advance'])->find();
        if (empty($order)) {
            throw new BaseException(['msg' => '订单不存在']);
        }
        return $order;
    }

    /**
     * 余额支付标记订单已支付
     */
    public function onPaymentByBalance($orderNo, $data)
    {
        // 获取订单详情
        $PaySuccess = new MasterPaySuccessService($orderNo, $data);
        // 发起余额支付
        $status = $PaySuccess->onPaySuccess(OrderPayTypeEnum::BALANCE, $data);
        if (!$status) {
            $this->error = $PaySuccess->getError();
        }
        return $status;
    }

    /**
     * 构建微信支付请求
     */
    protected static function onPaymentByWechat($user, $order_no, $pay_source, $online_money, $multiple)
    {
        return PaymentService::wechat(
            $user,
            $order_no,
            OrderTypeEnum::MASTER,
            $pay_source,
            $online_money,
            $multiple
        );
    }

    /**
     * 构建支付宝请求
     */
    protected static function onPaymentByAlipay($user, $order_no, $pay_source, $online_money, $multiple)
    {
        return PaymentService::alipay(
            $user,
            $order_no,
            OrderTypeEnum::MASTER,
            $pay_source,
            $online_money,
            $multiple
        );
    }

    /**
     * 待支付订单详情
     */
    public static function getPayDetail($orderNo)
    {
        $model = new static();
        return $model->where(['trade_no' => $orderNo, 'pay_status' => 10, 'is_delete' => 0])->with(['product', 'user', 'advance'])->find();
    }

    /**
     * 构建支付请求的参数
     */
    public static function onOrderPayment($user, $order_no, $payType, $pay_source, $online_money, $multiple)
    {
        //如果来源是h5,首次不处理，payH5再处理
//        if ($pay_source == 'h5') {
//            return [];
//        }
        if ($payType == OrderPayTypeEnum::WECHAT) {
            return self::onPaymentByWechat($user, $order_no, $pay_source, $online_money, $multiple);
        }
        if ($payType == OrderPayTypeEnum::ALIPAY) {
            if (config('pay.huifu_enabled', false)) {
                $tradeType = 'A_NATIVE';
                return self::onPaymentByHuifuAliPay($user, $order_no, $pay_source, $online_money, $multiple, $tradeType);
            }
            return self::onPaymentByAlipay($user, $order_no, $pay_source, $online_money, $multiple);
        }
        return [];
    }

    public static function onPaymentByHuifuAliPay($user, $order_no, $pay_source, $online_money, $multiple, $tradeType)
    {
        return PaymentService::huifuAlipay($user, $order_no, $pay_source, $online_money, $multiple, $tradeType);
    }

    /**
     * 判断当前订单是否允许核销
     */
    public function checkExtractOrder(&$order)
    {
        if (
            $order['pay_status']['value'] == OrderPayStatusEnum::SUCCESS
            && $order['delivery_type']['value'] == DeliveryTypeEnum::EXTRACT
            && $order['delivery_status']['value'] == 10
        ) {
            return true;
        }
        $this->setError('该订单不能被核销');
        return false;
    }

    /**
     * 当前订单是否允许申请售后
     */
    public function isAllowRefund()
    {
        if (!WzNormalZoneProductService::allowsAfterSaleAndComment((int)($this['zone_type'] ?? 0))) {
            return false;
        }
        // 必须是已发货的订单
        if ($this['delivery_status']['value'] != 20) {
            return false;
        }
        // 允许申请售后期限(天)
        $refundDays = SettingModel::getItem('trade')['order']['refund_days'];
        // 不允许售后
        if ($refundDays == 0) {
            return false;
        }
        // 当前时间超出允许申请售后期限
        if (
            $this['receipt_status'] == 20
            && time() > ($this['receipt_time'] + ((int)$refundDays * 86400))
        ) {
            return false;
        }
        return true;
    }

    /**
     * 获取活动订单
     * 已付款，未取消
     */
    public static function getPlusOrderNum($user_id, $product_id, $order_source)
    {
        $model = new static();
        return $model->alias('order')->where('order.user_id', '=', $user_id)
            ->join('order_product', 'order_product.order_id = order.order_id', 'left')
            ->where('order_product.product_source_id', '=', $product_id)
            ->where('order.pay_status', '=', 20)
            ->where('order.order_source', '=', $order_source)
            ->where('order.order_status', '<>', 20)
            ->sum('total_num');
    }

    /**
     * 累计成交笔数
     */
    public static function getTotalPayOrder($shop_supplier_id)
    {
        //累积成交笔数
        return (new static())->where('shop_supplier_id', '=', $shop_supplier_id)
            ->where('pay_status', '=', 20)
            ->where('order_status', 'in', [10, 30])
            ->count();
    }

    public static function getTodayPayOrder($shop_supplier_id)
    {
        //开始
        $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
        //结束
        $endToday = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;
        //今日成交笔数
        return (new static())->where('shop_supplier_id', '=', $shop_supplier_id)
            ->where('pay_status', '=', 20)
            ->where('order_status', 'in', [10, 30])
            ->whereBetweenTime('create_time', $beginToday, $endToday)
            ->count();
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

    /**
     * 获取直播订单
     */
    public function getLiveOrder($params, $user)
    {
        $model = $this;
        if (isset($params['room_id']) && $params['room_id']) {
            $model = $model->where('room_id', '=', $params['room_id'])
                ->where('user_id', '=', $user['user_id']);
        } else {
            $model = $model->where('shop_supplier_id', '=', $user['supplierUser']['shop_supplier_id']);
        }
        if (isset($params['pay_status']) && $params['pay_status']) {
            $model = $model->where('pay_status', '=', $params['pay_status']);
        }
        return $model->with(['product.image'])
            ->where('room_id', '>', 0)
            ->where('is_delete', '=', 0)
            ->order(['create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 主订单购买的数量
     * 未取消的订单
     */
    public static function getHasBuyOrderNum($user_id, $product_id)
    {
        $model = new static();
        return $model->alias('order')->where('order.user_id', '=', $user_id)
            ->join('order_product', 'order_product.order_id = order.order_id', 'left')
            ->where('order_product.product_id', '=', $product_id)
            ->where('order.order_source', '=', OrderSourceEnum::MASTER)
            ->where('order.order_status', '<>', 20)
            ->sum('total_num');
    }

    /**
     * 获取订单信息
     */
    public static function orderInfo($order_id, $user)
    {
        $orderID = explode(',', $order_id);
        $multiple = 0;
        $zone_type = 0;
        if (count($orderID) > 0) {
            $payPrice = OrderModel::where('order_id', 'in', $orderID)
                ->where('user_id', '=', $user['user_id'])
                ->where('pay_status', '=', 10)
                ->sum('pay_price');
            $multiple = 1;
            $zone_type = OrderModel::where('order_id', 'in', $orderID)
                ->where('user_id', '=', $user['user_id'])
                ->where('pay_status', '=', 10)->value('zone_type');
        } else {
            $payDetail = OrderModel::where('order_id', '=', $orderID)
                ->where('pay_status', '=', 10)
                ->find();
            $payPrice = $payDetail['pay_price'];
            $zone_type = $payDetail['zone_type'];
        }
        $payInfo['order_id'] = $orderID;
        $payInfo['pay_price'] = $payPrice;
        $payInfo['multiple'] = $multiple;
        $payInfo['zone_type'] = $zone_type;
        $payInfo['app_id'] = self::$app_id;
        return $payInfo;
    }

    /**
     * 待支付订单行（支付页消费券）
     */
    public static function getUnpaidOrderRows(array $orderIds, int $userId): array
    {
        if (empty($orderIds)) {
            return [];
        }
        return OrderModel::where('order_id', 'in', $orderIds)
            ->where('user_id', '=', $userId)
            ->where('pay_status', '=', 10)
            ->field('order_id,pay_price,order_no,zone_type,voucher_money')
            ->select()
            ->toArray();
    }

    /**
     * 创建订单支付信息
     */
    public function OrderPay($user, $params)
    {
        $payType = $params['payType'];
        $payment = '';
        $online_money = 0;
        $orderID = array_values(array_filter(array_map('intval', explode(',', (string)$params['order_id']))));
        if (empty($orderID)) {
            $this->error = '订单不存在';
            return false;
        }
        $orderRows = self::getUnpaidOrderRows($orderID, (int)$user['user_id']);
        if (empty($orderRows)) {
            $this->error = '订单不存在或已支付';
            return false;
        }
        $order_no = $this->orderNo();
        $multiple = count($orderID) > 1 ? 1 : 0;
        $orderInfo = null;
        $orderPayTotal = round((float)array_sum(array_column($orderRows, 'pay_price')), 2);
        $gpService     = new GreenPointsService();
        $voucherUsed   = GreenPointsService::sumReservedVoucherOnOrders($orderRows);

        if ((int)($params['use_voucher'] ?? 0) === 1) {
            if (!GreenPointsService::supportsSchema()) {
                $this->error = '积分功能未启用';
                return false;
            }
            $userVoucher   = $gpService->getUserBalances((int)$user['user_id'])['voucher'];
            $targetVoucher = GreenPointsService::calcPayVoucherAmount($params, $userVoucher, $orderPayTotal);
            if ($targetVoucher <= 0) {
                $this->error = '请输入正确的积分金额';
                return false;
            }
            if ($userVoucher < $targetVoucher) {
                $this->error = '积分余额不足';
                return false;
            }
            try {
                $gpService->reserveVoucherAtPay($orderRows, $targetVoucher);
            } catch (\Throwable $e) {
                $this->error = $e->getMessage() ?: '积分使用失败';
                return false;
            }
            $orderRows   = self::getUnpaidOrderRows($orderID, (int)$user['user_id']);
            $voucherUsed = GreenPointsService::sumReservedVoucherOnOrders($orderRows);
        } else {
            $gpService->clearReservedVoucherOnOrders($orderRows);
            $orderRows   = self::getUnpaidOrderRows($orderID, (int)$user['user_id']);
            $voucherUsed = 0;
        }

        $payPrice = round($orderPayTotal - $voucherUsed, 2);
        if ($payPrice < 0) {
            $payPrice = 0;
        }

        if ($multiple) {
            OrderTradeModel::where('order_id', 'in', $orderID)
                ->where('pay_status', '=', 10)
                ->update(['balance' => 0, 'online_money' => 0, 'out_trade_no' => $order_no]);
        } else {
            $orderInfo = OrderModel::where('order_id', '=', $orderID[0])
                ->where('pay_status', '=', 10)
                ->find();
            if (!$orderInfo) {
                $this->error = '订单不存在或已支付';
                return false;
            }
            $orderInfo->save(['balance' => 0, 'online_money' => 0, 'trade_no' => $order_no]);
        }
        if ($payPrice == 0) {
            $params['use_balance'] = 1;
        }
        if ($payPrice > 0 && empty($params['use_balance'])) {
            $this->error = '请使用余额支付';
            return false;
        }
        // 仅余额支付（消费券已在上方预占）；不支持微信等外部渠道
        if (!empty($params['use_balance'])) {
            if ((float)$user['balance'] < $payPrice) {
                $this->error = '余额不足，请减少积分外的支付金额或充值后再支付';
                return false;
            }
            $payType = OrderPayTypeEnum::BALANCE;
            if ($multiple == 1) {
                OrderTradeModel::where('order_id', 'in', $orderID)
                    ->where('pay_status', '=', 10)
                    ->update(['balance' => $payPrice, 'online_money' => 0]);
            } else {
                $orderInfo->save(['balance' => $payPrice, 'online_money' => 0]);
            }
            $data['multiple'] = $multiple;
            $data['attach'] = '{"pay_source":"' . $params['pay_source'] . '"}';
            if (!$this->onPaymentByBalance($order_no, $data)) {
                return false;
            }
        } elseif ($payPrice > 0) {
            $this->error = '请使用余额支付';
            return false;
        }

        $result['order_id'] = $params['order_id'];
        $result['payType'] = $payType;
        $result['payment'] = $payment;
        $result['order_no'] = $order_no;
        $result['pay_price']     = $payPrice;
        $result['use_balance']   = $params['use_balance'];
        $result['use_voucher']   = (int)($params['use_voucher'] ?? 0);
        $result['voucher_money'] = $voucherUsed;
        return $result;
    }

    /**
     * 获取尾款订单数据
     */
    public static function getFinalDetail($order_id)
    {
        $detail = self::detail($order_id);
        $detail['product'][0]['product_image'] = $detail['product'][0]['image']['file_path'];
        $detail['product'][0]['reduce_money'] = $detail['advance']['reduce_money'];
        $supplierData[] = [
            'shop_supplier_id' => $detail['shop_supplier_id'],
            'supplier' => $detail['supplier'],
            'productList' => $detail['product'],
            'order' => $detail
        ];
        return $supplierData;
    }

    /**
     * 取消订单
     */
    public function retract($user)
    {
        if ($this['order_status']['value'] != 21) {
            $this->error = '订单状态错误不可取消';
            return false;
        }
        return $this->save(['order_status' => 10]);
    }

    /**
     * 删除订单
     */
    public function setDelete()
    {
        if ($this['order_status']['value'] != 20) {
            $this->error = '订单状态错误不可删除';
            return false;
        }
        return $this->save(['is_delete' => 1, 'delete_time' => time()]);
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
