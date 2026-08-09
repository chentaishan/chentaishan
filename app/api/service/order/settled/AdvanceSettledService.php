<?php

namespace app\api\service\order\settled;

use app\api\model\order\Order as OrderModel;
use app\api\model\order\OrderProduct;
use app\api\model\order\OrderAddress as OrderAddress;
use app\api\model\product\Product as ProductModel;
use app\api\model\supplier\Supplier as SupplierModel;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\order\OrderTypeEnum;
use app\common\model\settings\Setting as SettingModel;
use app\api\model\store\Store as StoreModel;
use app\api\service\user\UserService;
use app\common\enum\settings\DeliveryTypeEnum;
use app\common\library\helper;
use app\common\service\delivery\ExpressService;
use app\common\service\BaseService;
use app\common\service\product\factory\ProductFactory;
use app\api\model\order\OrderAdvance as OrderAdvanceModel;

/**
 * 订单结算服务基类
 */
abstract class AdvanceSettledService extends BaseService
{
    /* $model OrderModel 订单模型 */
    public $model;

    //定金订单模型
    public $advanceModel;

    // 当前应用id
    protected $app_id;

    protected $user;

    // 订单结算商品列表
    protected $supplierData = [];

    protected $params;

    /**
     * 订单结算的规则
     * 主商品默认规则
     */
    protected $settledRule = [
        'is_agent' => true,     // 商品是否开启分销,最终还是支付成功后判断分销活动是否开启

    ];

    /**
     * 订单结算数据
     */
    protected $commonOrderData = [];
    /**
     * 订单结算数据
     */
    protected $orderData = [];
    /**
     * 订单来源
     */
    protected $orderSource;

    /**
     * 构造函数
     */
    public function __construct($user, $supplierData, $params)
    {
        $this->model = new OrderModel;
        $this->advanceModel = new OrderAdvanceModel;
        $this->app_id = OrderModel::$app_id;
        $this->user = $user;
        $this->supplierData = $supplierData;
        $this->params = $params;
    }

    /**
     * 订单确认-结算台
     */
    public function paySettlement()
    {
        // 验证商品状态, 是否允许购买
        $this->validateProductList();
        $orderTotalNum = 0;
        $orderTotalPrice = 0;
        $orderTotalPayPrice = 0;
        $orderTotalFrontPrice = 0;
        $expressPrice = 0;
        $this->commonOrderData = $this->getCommonOrderData();
        // 供应商
        $delivery = "";
        foreach ($this->supplierData as &$supplier) {
            // 整理订单数据
            $this->orderData = $this->getOrderData($supplier['shop_supplier_id'], $supplier['productList']);
            // 订单商品总数量
            $orderTotalNum += helper::getArrayColumnSum($supplier['productList'], 'total_num');
            // 处理配送方式
            if ($this->orderData['delivery'] == DeliveryTypeEnum::EXPRESS) {
                $this->setOrderExpress($supplier['productList']);
                $expressPrice += $this->orderData['express_price'];
            } elseif ($this->orderData['delivery'] == DeliveryTypeEnum::EXTRACT) {
                $this->orderData['store_id'] > 0 && $this->orderData['extract_store'] = StoreModel::detail($this->orderData['store_id']);
            }
            // 设置订单商品总金额(不含优惠折扣)
            $this->setOrderTotalFrontPrice($supplier['productList']);
            // 计算订单商品的实际付款金额
            $this->setOrderPayProductPayPrice($supplier['productList']);
            //计算最终支付金额
            $this->setOrderFinalPrice($supplier);
            $orderTotalPrice += $this->orderData['order_total_price'];
            $orderTotalPayPrice += $this->orderData['order_total_pay_price'];
            $orderTotalFrontPrice += $this->orderData['order_total_front_price'];
            $supplier['orderData'] = $this->orderData;
            $delivery = $this->orderData['delivery'];
        }
        $this->commonOrderData['show_address'] = $delivery == DeliveryTypeEnum::EXPRESS ? 1 : 0;
        $this->commonOrderData['show_extract'] = $delivery == DeliveryTypeEnum::EXTRACT ? 1 : 0;
        //订单数据
        $this->commonOrderData = array_merge([
            'order_total_num' => $orderTotalNum,  // 商品总数量
            'order_total_price' => helper::number2($orderTotalPrice),  // 商品总价
            'order_total_pay_price' => helper::number2($orderTotalPayPrice),  // 商品应付
            'order_total_front_price' => helper::number2($orderTotalFrontPrice),        // 商品定金
        ], $this->commonOrderData, $this->settledRule);
        // 返回订单数据
        return [
            'supplierList' => $this->supplierData,
            'orderData' => $this->commonOrderData
        ];
    }

    /**
     * 整理订单数据(结算台初始化),公共部分
     */
    private function getCommonOrderData()
    {
        // 积分设置
        $pointsSetting = SettingModel::getItem('points');
        return [
            // 默认地址
            'address' => $this->user['address_default'],
            // 是否存在收货地址
            'exist_address' => $this->user['address_id'] > 0,
            // 是否允许使用积分抵扣
            'is_allow_points' => true,
            // 是否使用积分抵扣
            'is_use_points' => $this->params['is_use_points'],
            // 支付方式
            'pay_type' => isset($this->params['pay_type']) ? $this->params['pay_type'] : OrderPayTypeEnum::WECHAT,
            'pay_source' => isset($this->params['pay_source']) ? $this->params['pay_source'] : '',
            // 系统设置
            'setting' => [
                'points_name' => $pointsSetting['points_name'],      // 积分名称
            ],
            //尾款立减金额
            'reduce_money' => $this->supplierData[0]['productList'][0]['reduce_money'], // 尾款立减金额
            //是否显示收货人地址
            'show_address' => 0,
            //是否显示自提联系人
            'show_extract' => 0
        ];
    }


    /**
     * 验证订单商品的状态
     * @return bool
     */
    abstract function validateProductList();

    /**
     * 创建定金订单
     */
    public function createPayOrder($order)
    {
        // 创建新的订单
        $supplier = $order['supplierList'][0];
        $this->model = new OrderModel;
        if ($supplier['orderData']['delivery'] == DeliveryTypeEnum::EXTRACT) {
            if (empty($supplier['orderData']['extract_store'])) {
                $this->error = '请先选择自提门店';
                return false;
            }
            if (empty($this->params['linkman']) || empty($this->params['phone'])) {
                $this->error = '请填写联系人和电话';
                return false;
            }
        }
        $this->model->transaction(function () use ($order, $supplier) {
            // 创建订单事件
            $this->createPayOrderEvent($order['orderData'], $supplier);
        });
        return $this->advanceModel['order_advance_id'];

    }

    /**
     * 设置订单的商品定金金额
     */
    private function setOrderTotalFrontPrice($productList)
    {
        // 订单商品的总金额
        $this->orderData['order_total_price'] = helper::number2(helper::getArrayColumnSum($productList, 'total_price'));
        // 订单商品的支付金额
        $this->orderData['order_total_pay_price'] = helper::number2(helper::bcsub($this->orderData['order_total_price'], $this->commonOrderData['reduce_money']));
        // 订单商品的定金总金额
        $this->orderData['order_total_front_price'] = helper::number2(helper::getArrayColumnSum($productList, 'total_front_price'));
    }

    /**
     * 计算订单商品的实际付款金额
     */
    private function setOrderPayProductPayPrice($productList)
    {
        // 商品总价 - 优惠抵扣
        foreach ($productList as &$product) {
            // 减去优惠券抵扣金额
            $value = $product['total_price'];
            // 减去立减金额
            if ($this->commonOrderData['reduce_money'] > 0) {
                $value = helper::bcsub($value, $this->commonOrderData['reduce_money']);
            }
            $product['total_pay_price'] = helper::number2($value);
        }

        return true;
    }

    /**
     * 整理订单数据(结算台初始化)
     */
    private function getOrderData($shop_supplier_id, $productList)
    {
        // 系统支持的配送方式 (后台设置)
        $deliveryType = SupplierModel::detail($shop_supplier_id)['logistics_type'];
        sort($deliveryType);
        // 积分设置
        $pointsSetting = SettingModel::getItem('points');
        $store_id = 0;
        if ($productList[0]['is_virtual'] == 1) {
            $delivery = 30;
        } else {
            $logistics = $productList[0]['logistics'];
            $delivery = isset($this->params['supplier']) ? $this->params['supplier'][$shop_supplier_id]['delivery'] : $deliveryType[0];
            if (count($logistics) == 1) {
                $deliveryType = $logistics;
                $delivery = $logistics[0];
            }
            if ($delivery == DeliveryTypeEnum::EXTRACT) {
                $store_id = isset($this->params['supplier']) ? $this->params['supplier'][$shop_supplier_id]['store_id'] : 0;
                if ($store_id == 0) {
                    $store_id = StoreModel::getDefault($shop_supplier_id);
                }
            }
        }
        return [
            // 配送类型
            'delivery' => $delivery,
            // 配送费用
            'express_price' => 0.00,
            // 当前用户收货城市是否存在配送规则中
            'intra_region' => true,
            // 自提门店信息
            'extract_store' => [],
            // 是否允许使用积分抵扣
            'is_allow_points' => false,
            // 是否使用积分抵扣
            'is_use_points' => $this->params['is_use_points'],
            // 支付方式
            'pay_type' => isset($this->params['pay_type']) ? $this->params['pay_type'] : OrderPayTypeEnum::WECHAT,
            // 系统设置
            'setting' => [
                'delivery' => $deliveryType,     // 支持的配送方式
                'points_name' => $pointsSetting['points_name'],      // 积分名称
            ],
            'deliverySetting' => $deliveryType,
            //门店id
            'store_id' => $store_id,
            //优惠券id
            'coupon_id' => 0,
            //优惠金额
            'coupon_money' => 0,
            //优惠券
            'couponList' => [],
            //平台优惠券
            'coupon_id_sys' => 0,
            //平台优惠券金额
            'coupon_money_sys' => 0,
        ];
    }

    /**
     * 订单配送-快递配送
     */
    private function setOrderExpress($productList)
    {
        // 设置默认数据：配送费用
        helper::setDataAttribute($productList, [
            'express_price' => 0,
        ], true);
        // 当前用户收货城市id
        $cityId = $this->user['address_default'] ? $this->user['address_default']['city_id'] : null;

        // 初始化配送服务类
        $ExpressService = new ExpressService(
            $this->app_id,
            $cityId,
            $productList,
            OrderTypeEnum::MASTER
        );

        // 获取不支持当前城市配送的商品
        $notInRuleProduct = $ExpressService->getNotInRuleProduct();

        // 验证商品是否在配送范围
        $this->orderData['intra_region'] = ($notInRuleProduct === false);

        if (!$this->orderData['intra_region']) {
            $notInRuleProductName = $notInRuleProduct['product_name'];
            $this->error = "很抱歉，您的收货地址不在商品 [{$notInRuleProductName}] 的配送范围内";
            return false;
        } else {
            // 计算配送金额
            $ExpressService->setExpressPrice();
        }

        // 订单总运费金额
        $this->orderData['express_price'] = helper::number2($ExpressService->getTotalFreight());
        return true;
    }

    /**
     * 创建订单事件
     */
    private function createPayOrderEvent($commomOrder, $supplier)
    {
        // 新增订单记录
        $status = $this->addPay($commomOrder, $supplier);
        if ($supplier['orderData']['delivery'] == DeliveryTypeEnum::EXPRESS) {
            // 记录收货地址
            $this->saveOrderAddress($commomOrder['address'], $status);
        } elseif ($supplier['orderData']['delivery'] == DeliveryTypeEnum::EXTRACT) {
            // 记录自提信息
            $this->saveOrderExtract($this->params['linkman'], $this->params['phone']);
        }
        // 保存订单商品信息
        $this->saveOrderPayProduct($supplier, $status, $commomOrder);
        // 保存定金订单信息
        $this->saveOrderAdvancePay($supplier, $status, $commomOrder);
        // 更新商品库存 (针对下单减库存的商品)
        ProductFactory::getFactory($this->orderSource['source'])->updateAdvanceProductStock($supplier['productList']);
        return $status;
    }

    /**
     * 新增订单记录
     */
    private function addPay($commomOrder, $supplier)
    {
        $order = $supplier['orderData'];
        // 订单数据
        $data = [
            'user_id' => $this->user['user_id'],
            'order_no' => $this->model->orderNo(),
            'trade_no' => $this->model->orderNo(),
            'total_price' => $order['order_total_price'],
            'order_price' => $order['order_total_pay_price'],
            'pay_price' => $order['order_total_pay_price'],
            'delivery_type' => $supplier['orderData']['delivery'],
            'pay_type' => $commomOrder['pay_type'],
            'order_source' => $this->orderSource['source'],
            'shop_supplier_id' => $supplier['shop_supplier_id'],
            'app_id' => $this->app_id,
            'virtual_auto' => $supplier['productList'][0]['virtual_auto'],
            'supplier_money' => $order['supplier_money'],
            'sys_money' => $order['sys_money'],
            'is_agent' => $this->settledRule['is_agent'] ? 1 : 0,
        ];
        if ($supplier['orderData']['delivery'] == DeliveryTypeEnum::EXPRESS) {
            $data['express_price'] = $order['express_price'];
        } elseif ($supplier['orderData']['delivery'] == DeliveryTypeEnum::EXTRACT) {
            $data['extract_store_id'] = $order['extract_store']['store_id'];
        }
        // 保存订单记录
        $this->model->save($data);
        return $this->model['order_id'];
    }

    /**
     * 新增定金订单记录
     */
    private function saveOrderAdvancePay($supplier, $order_id, $commomOrder)
    {

        $advance = $supplier['productList'][0]['advance'];
        $config = SettingModel::getItem('advance');
        // 订单数据
        $data = [
            'order_id' => $order_id,
            'user_id' => $this->user['user_id'],
            'order_no' => $this->advanceModel->orderNo(),
            'trade_no' => $this->advanceModel->orderNo(),
            'pay_price' => $commomOrder['order_total_front_price'],
            'end_time' => $advance['end_time'],
            'pay_type' => $commomOrder['pay_type'],
            'advance_product_id' => $advance['advance_product_id'],
            'advance_product_sku_id' => $supplier['productList'][0]['advance_sku']['advance_product_sku_id'],
            'money_return' => $config['money_return'],
            'reduce_money' => $commomOrder['reduce_money'],
            'shop_supplier_id' => $supplier['shop_supplier_id'],
            'app_id' => $this->app_id,

        ];
        // 结束支付时间
        $closeMinters = $config['end_time'];
        $config['end_time'] > 0 && $data['pay_end_time'] = time() + ($closeMinters * 60);
        // 保存订单记录
        $this->advanceModel->save($data);
        return $this->advanceModel['order_advance_id'];
    }

    /**
     * 记录收货地址
     */
    private function saveOrderAddress($address, $order_id)
    {
        $model = new OrderAddress();
        if ($address['region_id'] == 0 && !empty($address['district'])) {
            $address['detail'] = $address['district'] . ' ' . $address['detail'];
        }
        return $model->save([
            'order_id' => $order_id,
            'user_id' => $this->user['user_id'],
            'app_id' => $this->app_id,
            'name' => $address['name'],
            'phone' => $address['phone'],
            'province_id' => $address['province_id'],
            'city_id' => $address['city_id'],
            'region_id' => $address['region_id'],
            'detail' => $address['detail'],
        ]);
    }

    /**
     * 保存上门自提联系人
     */
    private function saveOrderExtract($linkman, $phone)
    {
        // 记忆上门自提联系人(缓存)，用于下次自动填写
        UserService::setLastExtract($this->model['user_id'], trim($linkman), trim($phone));
        // 保存上门自提联系人(数据库)
        return $this->model->extract()->save([
            'linkman' => trim($linkman),
            'phone' => trim($phone),
            'user_id' => $this->model['user_id'],
            'app_id' => $this->app_id,
        ]);
    }

    /**
     * 保存订单商品信息
     */
    private function saveOrderPayProduct($supplier, $status, $commomOrder)
    {
        // 订单商品列表
        $productList = [];
        foreach ($supplier['productList'] as $product) {
            $item = [
                'order_id' => $status,
                'user_id' => $this->user['user_id'],
                'app_id' => $this->app_id,
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'image_id' => $product['image'][0]['image_id'],
                'deduct_stock_type' => $product['deduct_stock_type'],
                'spec_type' => $product['spec_type'],
                'spec_sku_id' => $product['product_sku']['spec_sku_id'],
                'product_sku_id' => $product['product_sku']['product_sku_id'],
                'product_attr' => $product['product_sku']['product_attr'],
                'content' => $product['content'],
                'product_no' => $product['product_sku']['product_no'],
                'product_price' => $product['product_price'],
                'line_price' => $product['product_sku']['line_price'],
                'product_weight' => $product['product_sku']['product_weight'],
                'total_num' => $product['total_num'],
                'total_price' => $product['total_price'],
                'total_pay_price' => $product['total_pay_price'],
                'virtual_content' => $product['virtual_content'],
                'is_agent' => $product['is_agent'],
                'is_ind_agent' => $product['is_ind_agent'],
                'agent_money_type' => $product['agent_money_type'],
                'first_money' => $product['first_money'],
                'second_money' => $product['second_money'],
                'third_money' => $product['third_money'],
                'supplier_money' => $product['supplier_money'],
            ];
            // 记录订单商品来源id
            $item['product_source_id'] = isset($product['product_source_id']) ? $product['product_source_id'] : 0;
            // 记录订单商品sku来源id
            $item['sku_source_id'] = isset($product['sku_source_id']) ? $product['sku_source_id'] : 0;
            $productList[] = $item;
        }

        $model = new OrderProduct();
        return $model->saveAll($productList);
    }

    /**
     * 获取所有支付价格
     */
    private function setOrderFinalPrice($supplier)
    {
        $sys_percent = (new SupplierModel)->where('shop_supplier_id', '=', $supplier['shop_supplier_id'])->value('commission_rate');
        $supplier_percent = 100 - $sys_percent;
        // 供应商结算金额，包括运费
        $this->orderData['supplier_money'] = helper::number2($this->orderData['order_total_pay_price'] * $supplier_percent / 100 + $this->orderData['express_price']);
        // 平台分佣金额
        $this->orderData['sys_money'] = helper::number2($this->orderData['order_total_pay_price'] * $sys_percent / 100);
        // 实际支付金额
        $this->orderData['order_total_pay_price'] = helper::number2(helper::bcadd($this->orderData['order_total_pay_price'], $this->orderData['express_price']));
        // 结算金额不包括运费
        foreach ($supplier['productList'] as &$product) {
            $product['supplier_money'] = $this->orderData['supplier_money'];
            $product['sys_money'] = helper::number2($product['total_pay_price'] * $sys_percent / 100);
        }

    }
}