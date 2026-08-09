<?php

namespace app\api\controller\supplier;

use app\api\controller\Controller;
use app\api\model\supplier\DepositOrder as DepositOrderModel;
use app\api\model\supplier\Supplier as SupplierModel;
use app\api\model\plus\coupon\Coupon as CouponModel;
use app\api\model\page\Ad as AdModel;
use app\api\model\order\Order as OrderModel;
use app\api\model\user\Visit as VisitModel;
use app\common\service\statistics\OrderService;
use app\supplier\service\statistics\UserService;
use app\supplier\model\order\OrderSettled as OrderSettledModel;
use app\api\model\settings\Setting as SettingModel;
use app\api\model\plus\live\Room as RoomModel;
use app\common\model\supplier\Service as ServiceModel;
use app\api\model\plus\chat\Chat as ChatModel;
use app\common\model\app\App as AppModel;
use app\common\enum\order\OrderTypeEnum;
use app\api\model\plus\chat\ChatRelation as ChatRelationModel;
use app\api\model\plus\agent\Order as AgentOrderModel;

/**
 * 供应商
 */
class Index extends Controller
{

    //店铺列表
    public function list()
    {
        $param = $this->postData();
        $SupplierModel = new SupplierModel;
        $list = $SupplierModel->supplierList($param);
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * H5 可展示商户列表
     */
    public function visibleList()
    {
        $param = $this->postData();
        $SupplierModel = new SupplierModel;
        $list = $SupplierModel->visibleList($param);
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 供应商中心主页
     */
    public function index()
    {
        $data = $this->postData();
        $supplier = new SupplierModel;
        $user = $this->getUser(false);
        //获取店铺信息
        $detail = $supplier->getDetail($data, $user);
        if (!$detail) {
            return $this->renderError('店铺不存在');
        }
        //banner图
        $AdModel = new AdModel;
        $adList = $AdModel->getIndex(['shop_supplier_id' => $data['shop_supplier_id']], 5);
        //优惠券
        $dataCoupon['shop_supplier_id'] = $data['shop_supplier_id'];
        $model = new CouponModel;
        $couponList = $model->getWaitList($dataCoupon, $user, 1);
        // 访问记录
        (new VisitModel())->addVisit($user, $detail, $data['visitcode'], null);
        //直播列表
        $model = new RoomModel();
        $liveList = $model->getStoreList($this->postData());
        //是否显示直播
        $liv_status = SettingModel::getItem('live');
        //是否开启客服
        $service_open = SettingModel::getSysConfig()['service_open'];
        //店铺客服信息
        $mp_service = ServiceModel::detail($data['shop_supplier_id']);
        //返回客服id
        $detail['chat_user_id'] = (new ChatRelationModel)->getChatUser($data['shop_supplier_id'], $user);
        return $this->renderSuccess('', compact('detail', 'couponList', 'liveList', 'liv_status', 'service_open', 'mp_service', 'adList'));
    }

    //成交数据
    public function tradeData($url = '')
    {
        $data = $this->postData();
        $user = $this->getUser();
        $data['shop_supplier_id'] = $this->getSupplierUser($user)['shop_supplier_id'];
        if (!$data['shop_supplier_id'] > 0) {
            return $this->renderError('您还未开通店铺');
        }
        $is_open = SettingModel::getItem('live')['is_open'];
        //累积成交笔数
        $totalCount = OrderModel::getTotalPayOrder($data['shop_supplier_id']);
        //今日成交笔数
        $todayCount = OrderModel::getTodayPayOrder($data['shop_supplier_id']);
        //商户详情
        $supplier = SupplierModel::detail($data['shop_supplier_id'], ['logo', 'category']);
        // 客服消息
        $msg_count = ChatModel::getNoReadCount($user['user_id']);
        return $this->renderSuccess('', compact('totalCount', 'todayCount', 'supplier', 'is_open', 'msg_count'));
    }

    /**
     * 付押金
     */
    public function deposit()
    {
        // 用户信息
        $user = $this->getUser();
        $supplier = SupplierModel::detail($this->getSupplierUser($user)['shop_supplier_id'], ['category']);
        // 类目
        $category = $supplier['category'];
        if ($this->request->isGet()) {
            // 返回结算信息
            return $this->renderSuccess('', compact('category'));
        }
        // 生成订单
        $model = new DepositOrderModel;
        $order_id = $model->createOrder($user, $supplier);
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
        $model = DepositOrderModel::getUserOrderDetail($order_id, $user['user_id']);
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
        if ($model['pay_status'] != 10) {
            return $this->renderError($model->getError() ?: '订单已支付');
        }
        // 构建微信支付请求
        $DepositOrderModel = new DepositOrderModel;
        $payInfo = $DepositOrderModel->OrderPay($params, $model, $user);
        if (!$payInfo) {
            return $this->renderError($DepositOrderModel->getError() ?: '订单支付失败');
        }
        // 支付状态提醒
        return $this->renderSuccess('', [
            'order_id' => $order_id,   // 订单id
            'pay_type' => $payInfo['payType'],  // 支付方式
            'payment' => $payInfo['payment'],   // 微信支付参数
            'order_type' => OrderTypeEnum::CASH, //订单类型
            'use_balance' => $payInfo['use_balance']// 是否使用余额
        ]);
    }

    //店铺数据
    public function storedata()
    {
        $user = $this->getUser();
        $shop_supplier_id = $this->getSupplierUser($user)['shop_supplier_id'];
        //成交量
        $order = (new OrderService($shop_supplier_id))->getData();
        // 访问量
        $visit = (new UserService($shop_supplier_id))->getData();
        //订单结算
        $ordersettle = (new OrderSettledModel())->getList($shop_supplier_id, $this->postData());
        return $this->renderSuccess('', compact('order', 'visit', 'ordersettle'));
    }

    /**
     * 详情
     */
    public function settledetail($settled_id)
    {
        $model = OrderSettledModel::detail($settled_id);
        $order = OrderModel::detail($model['order_id'], ['product' => ['image', 'refund']]);
        $agent = (new AgentOrderModel)->where('order_id', '=', $model['order_id'])->find();
        $order['first_money'] = $agent ? $agent['first_money'] : 0;
        $order['second_money'] = $agent ? $agent['second_money'] : 0;
        $order['third_money'] = $agent ? $agent['third_money'] : 0;
        return $this->renderSuccess('', compact('model', 'order'));
    }

    /**
     * 商户详情
     */
    public function detail($shop_supplier_id)
    {
        $detail = (new SupplierModel())->getDetail(['shop_supplier_id' => $shop_supplier_id], $this->getUser());
        //是否开启客服
        $service_open = SettingModel::getSysConfig()['service_open'];
        //店铺客服信息
        $mp_service = ServiceModel::detail($shop_supplier_id);
        //返回客服id
        $detail['chat_user_id'] = (new ChatRelationModel)->getChatUser($shop_supplier_id, $this->getUser());
        return $this->renderSuccess('', compact('detail', 'mp_service', 'service_open'));
    }
}