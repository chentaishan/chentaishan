<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\api\controller\product\Product;
use app\api\model\order\Order as OrderModel;
use app\api\model\plus\chat\ChatRelation as ChatRelationModel;
use app\api\model\settings\Setting as SettingModel;
use app\api\service\pay\PayService;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\exception\BaseException;
use app\common\model\order\CloudBillRecord;
use app\common\model\order\CloudCardApply;
use app\common\model\order\CloudPayApply;
use app\common\model\order\CloudPayConfig;
use app\common\model\order\CloudRatio;
use app\common\model\order\CloudUserCard;
use app\common\model\settings\Express as ExpressModel;
use app\common\model\user\User;
use app\common\service\qrcode\ExtractService;
use app\common\model\supplier\Service as ServiceModel;
use app\common\model\supplier\User as SupplierUserModel;
use app\common\model\app\App as AppModel;
use app\common\enum\order\OrderTypeEnum;
use app\common\service\activity\ActivityRewardService;
use app\common\service\activity\WzPerformanceService;
use app\common\service\greenpoints\GreenPointsService;
use app\common\service\qrcode\UserCodeService;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\PointsLog as PointsLogModel;
use app\common\model\user\WithdrawalRecord;
use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use think\facade\Cache;
use think\facade\Db;

/**
 * 我的订单
 */
class Order extends Controller
{
    private const PAY_TYPE_VOUCHER = 60;

    // user
    private $user;

    /**
     * 构造方法
     */
    public function initialize()
    {
        parent::initialize();
        $this->user = $this->getUser();   // 用户信息

    }

    /**
     * 我的订单列表
     */
    public function lists($dataType)
    {
        $data = $this->postData();
        $model = new OrderModel;
        $list = $model->getList($this->user['user_id'], $dataType, $data);
        $app = AppModel::detail();
        $mch_id = $app['mchid'];
        return $this->renderSuccess('', compact('list', 'mch_id'));
    }

    /**
     * 订单详情信息
     */
    public function detail($order_id, $pay_source = '')
    {
        // 订单详情
        $model = OrderModel::getOrderDetail($order_id, $this->user['user_id']);
        // 剩余支付时间
        if ($model['pay_status']['value'] == 10 && $model['order_status']['value'] != 20 && $model['pay_end_time'] != 0) {
            $model['pay_end_time'] = $this->formatPayEndTime($model['pay_end_time'] - time());
        } else {
            $model['pay_end_time'] = '';
        }
        if (isset($model['pay_time']) && $model['pay_time']) {
            $model['pay_time'] = date('Y-m-d H:i:s', $model['pay_time']);
        }
        if (isset($model['receipt_time']) && $model['receipt_time']) {
            $model['receipt_time'] = date('Y-m-d H:i:s', $model['receipt_time']);
        }
        if (isset($model['delivery_time']) && $model['delivery_time']) {
            $model['delivery_time'] = date('Y-m-d H:i:s', $model['delivery_time']);
        }
        //查询是否多个商户订单
        $model['orderSupplierCount'] = (new OrderModel)->getOrderSupplierCount($order_id);
        // 该订单是否允许申请售后
        $model['isAllowRefund'] = $model->isAllowRefund();
        $model['supplier']['supplier_user_id'] = (new SupplierUserModel())->where('shop_supplier_id', '=', $model['shop_supplier_id'])->value('supplier_user_id');
        $app = AppModel::detail();
        $mch_id = $app['mchid'];
        //返回客服id
        $chat_user_id = (new ChatRelationModel)->getChatUser($model['shop_supplier_id'], $this->user);
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
            'mch_id' => $mch_id,
            'chat_user_id' => $chat_user_id
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
        $order['pay_price'] = round($order['pay_price'], 2);
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
     * 获取多包裹物流信息
     */
    public function multiExpress($order_id, $express_no, $express_id)
    {
        if (!$order_id || !$express_no || !$express_id) {
            return $this->renderError('参数错误');
        }
        $detail = ExpressModel::detail($express_id);
        // 订单信息
        $order = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
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
        $data['expressName'] = $detail['express_name'];
        $data['expressNo'] = $express_no;
        $data['expressId'] = $express_id;
        return $this->renderSuccess('', compact('data', 'express'));
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
    public function pay()
    {
        $params = $this->postData();
        $payDetail = OrderModel::orderInfo($params['order_id'], $this->user);
        $zoneType = (int)($payDetail['zone_type'] ?? 0);
        if ($this->request->isGet()) {
            $orderIds  = array_values(array_filter(array_map('intval', explode(',', (string)($params['order_id'] ?? '')))));
            $orderRows = OrderModel::getUnpaidOrderRows($orderIds, (int)$this->user['user_id']);
            $orderPayPrice = round((float)($payDetail['pay_price'] ?? 0), 2);
            $applied_voucher_money = GreenPointsService::sumReservedVoucherOnOrders($orderRows);
            $payPrice = round($orderPayPrice - $applied_voucher_money, 2);
            if ($payPrice < 0) {
                $payPrice = 0;
            }
            $balance  = $this->user['balance'];
            $voucher = 0;
            $max_voucher_money = 0;
            $can_use_voucher = 0;
            if (GreenPointsService::supportsSchema()) {
                $voucher = (new GreenPointsService())->getUserBalances((int)$this->user['user_id'])['voucher'];
                $max_voucher_money = round(min((float)$voucher, $orderPayPrice), 2);
                $can_use_voucher = $max_voucher_money > 0 ? 1 : 0;
            }
            if(in_array($zoneType, [1])) {
                $payTypes = [
                    ['name' => '余额', 'value' => OrderPayTypeEnum::BALANCE, 'key' => 'balance', 'enabled' => 1],
                    //['name' => '积分', 'value' => self::PAY_TYPE_VOUCHER, 'key' => 'voucher', 'enabled' => $can_use_voucher],
                ];
            }
            else{
                $payTypes = [
                    ['name' => '余额', 'value' => OrderPayTypeEnum::BALANCE, 'key' => 'balance', 'enabled' => 1],
                    ['name' => '积分', 'value' => self::PAY_TYPE_VOUCHER, 'key' => 'voucher', 'enabled' => $can_use_voucher],
                ];
            }

            $order_pay_price = $orderPayPrice;
            return $this->renderSuccess('', compact(
                'payTypes',
                'payPrice',
                'order_pay_price',
                'balance',
                'zoneType',
                'voucher',
                'max_voucher_money',
                'can_use_voucher'
            ));
        }
        //判断合伙人是否可以购买
        $user = $this->user;
        if($zoneType == 0){
            return $this->renderError('购买商品分区不存在');
        }
        if($zoneType == 4){
            if($user['is_partner']) {
                return $this->renderError('合伙区只可以购买一单');
            }
            $maxPartner = intval(CloudRatio::where('ratio_id',82)->value('num'));
            $currPartner = User::where(['is_delete'=>0,'is_partner'=>1,'is_partner_out'=>0])->count();
            if($currPartner >= $maxPartner){
                return $this->renderError('合伙人限定'.$maxPartner.'人，有人出局空出名额后，再认购');
            }
        }
        //支付
        $payTypeParam = (int)($params['payType'] ?? 0);
        if ($payTypeParam === self::PAY_TYPE_VOUCHER) {
            $params['payType'] = OrderPayTypeEnum::BALANCE;
            $params['use_voucher'] = 1;
            $params['use_balance'] = 1;
            $payTypeParam = OrderPayTypeEnum::BALANCE;
        }
        if ($payTypeParam === OrderPayTypeEnum::ALIPAY) {
            return $this->renderError('暂不支持支付宝支付');
        }
        if ($payTypeParam !== OrderPayTypeEnum::BALANCE) {
            return $this->renderError('仅支持余额支付，请勾选余额并确保余额充足');
        }

        $payPassword = trim((string)($params['pay_password'] ?? ''));
        $payPasswordError = User::getPayPasswordVerifyError($this->user, $payPassword);
        if ($payPasswordError !== null) {
            return $this->renderError($payPasswordError);
        }

        // 订单支付事件
        $model = new OrderModel;
        if (!$model->onPay($params, $user)) {
            return $this->renderError($model->getError() ?: '订单支付失败');
        }

        // 构建支付请求
        $payInfo = $model->OrderPay($user, $params);
        if (!$payInfo) {
            return $this->renderError($model->getError() ?: '订单支付失败');
        }
        //修改为合伙人
        if($zoneType == 4) {
            $maxPartnerOut = CloudRatio::where('ratio_id',81)->value('num');
            User::where(['user_id'=>$user['user_id']])->update(['is_partner'=>1,'partner_dividend_out'=>$maxPartnerOut]);
        }
        // 支付成功执行优品区/消费区奖励
        // 支付状态提醒
        return $this->renderSuccess('', [
            'order_id' => $payInfo['order_id'],   // 订单id
            'pay_type' => $payInfo['payType'],  // 支付方式
            'payment' => $payInfo['payment'],   // 微信支付参数
            'order_type' => OrderTypeEnum::MASTER, //订单类型
            'use_balance' => $payInfo['use_balance'],// 是否使用余额
            'use_voucher' => $payInfo['use_voucher'] ?? 0,
            'voucher_money' => $payInfo['voucher_money'] ?? 0,
            'return_Url' => $params['pay_source'] == 'h5' ? urlencode(base_url() . "h5/pages/order/myorder") : '', //h5支付跳转地址
        ]);
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

    /**
     * 撤回取消订单
     */
    public function retract($order_id)
    {
        $model = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
        if ($model->retract($this->user)) {
            return $this->renderSuccess('订单取消成功');
        }
        return $this->renderError($model->getError() ?: '订单取消失败');
    }

    /**
     * 删除订单
     */
    public function delete($order_id)
    {
        $model = OrderModel::getUserOrderDetail($order_id, $this->user['user_id']);
        if ($model->setDelete($this->user)) {
            return $this->renderSuccess('订单删除成功');
        }
        return $this->renderError($model->getError() ?: '订单删除失败');
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


    //生成推广海报
    public function getQrCode()
    {
        $user = $this->getUser(false);
        $data = $this->postData();
        $source = $data['source'] ?? 'h5';
        if ($user) {
            // if($user['grading'] > 0){
            $id = $user['user_id'];
            $Qrcode = new UserCodeService($user, $id, $source);
            return $this->renderSuccess('', [
                'qrcode' => $Qrcode->getImage(),
            ]);
            // }
            // else{
            //     return $this->renderError("普通用户不能生成推广码");
            // }
        }
        return $this->renderError("生成二维码失败");
    }


    //发起充值申请
    public function payApply()
    {
        $param = $this->request->post();
        if (!isset($param['price']) || empty($param['price'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['type']) || empty($param['type'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['image']) || empty($param['image'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['real_name']) || empty($param['real_name'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['account']) || empty($param['account'])) {
            return $this->renderError('缺少参数');
        }


        CloudPayApply::create([
            'user_id' => $this->user['user_id'],
            'nickName' => $this->user['nickName'],
            'price' => $param['price'],
            'image' => $param['image'],
            'real_name' => $param['real_name'],
            'account' => $param['account'],
            'type' => $param['type'],
            'status' => 1,
        ]);

        return $this->renderSuccess('发起成功，请等待审核');
    }

    //充值配置
    public function getPayConfig()
    {
        $data = CloudPayConfig::order('pay_config_id asc')->limit(1)->find();

        return $this->renderSuccess('成功', $data);
    }


    //进行货币数据变更 添加货币变更记录 参数： 用户id 变动类型inc增加dec减少   货币类型文字  货币类型type数字  变动价值数额  文本内容
    public static function billAdd($userId, $action, $cur_type, $cur_type_num, $num, $content, $order_id = 0)
    {
        $user = \app\common\model\user\User::where(['user_id' => $userId])->field("user_id,$cur_type")->find();

        if(!isset($user)){
            $user[$cur_type] = 0;
        }

        \app\common\model\user\User::where(['user_id' => $userId])->update([
            $cur_type => [$action, $num]
        ]);

        CloudBillRecord::create([
            'user_id' => $userId,
            'price' => $num,
            'ac_type' => $action == 'inc' ? 1 : 2,
            'cur_type' => $cur_type_num,
            'profit' => $content,
            'order_id' => $order_id,
            'old_num' => $user["$cur_type"],
            'new_num' => $action == 'inc' ? $user["$cur_type"] + $num : $user["$cur_type"] - $num,
        ]);

        if ($cur_type_num == 7 && $action == 'inc') {
            \app\common\model\user\User::where(['user_id' => $userId])->update([
                'all_unusable_integral' => [$action, $num]
            ]);
        }

        if ($cur_type_num == 11 && $action == 'inc') {
            \app\common\model\user\User::where(['user_id' => $userId])->update([
                'all_unusable_bonus' => [$action, $num]
            ]);
        }

    }

    //获取账单列表 1=余额 2=积分
    public function getBillRecord()
    {
        $param = $this->request->post();
        if (!isset($param['type']) || empty($param['type'])) {
            return $this->renderError('缺少参数');
        }
        if (isset($param['time']) || !empty($param['time'])) {
            $list = CloudBillRecord::where(['cur_type' => $param['type']])
                ->where(['user_id' => $this->user['user_id']])
                ->where('create_time', '>=', $param['time'] . ' 00:00:00')
                ->where('create_time', '<=', $param['time'] . ' 23:59:59')
                ->order('bill_record_id desc')
                ->paginate($param);

            return $this->renderSuccess('', compact('list'));

        }
        $list = CloudBillRecord::where(['cur_type' => $param['type']])
            ->where(['user_id' => $this->user['user_id']])
            ->order('bill_record_id desc')
            ->paginate($param);

        return $this->renderSuccess('', compact('list'));

    }

    public function getActivityAsset()
    {
        try {
            $service = new ActivityRewardService();
            $asset = $service->getUserAsset($this->user['user_id']);
            if (empty($asset)) {
                return $this->renderError('当前数据库未完成活动奖励字段升级');
            }

            $energyService = new \app\common\service\activity\EnergyRewardService();
            $asset = array_merge($asset, $energyService->getEnergyAssetSummary((int)$this->user['user_id']));

            $asset['withdrawal_config'] = \app\common\service\settings\WithdrawalConfigService::get();

            $asset['job_grade_name']    = \app\common\service\activity\WzUserIdentityService::getJobGradeDisplayText($this->user);
            $asset['is_store']          = (int)($this->user['is_store'] ?? 0);
            $asset['is_store_text']     = \app\common\service\activity\WzUserIdentityService::getStoreText($this->user);
            $asset['is_partner']        = \app\common\service\activity\WzUserIdentityService::isPartner($this->user) ? 1 : 0;
            $asset['is_partner_text']   = \app\common\service\activity\WzUserIdentityService::getPartnerText($this->user);
            $asset['agent_level_text']  = \app\common\service\activity\WzUserIdentityService::getAgentLevelText($this->user);
            $asset['can_access_agent_stock'] = \app\common\service\activity\WzUserIdentityService::canAccessAgentStockZone($this->user);
            $asset['identity_text']     = \app\common\service\activity\WzUserIdentityService::getIdentityDisplayText($this->user);

            // 直推人数(基于referee_id)
            $asset['direct_push_count'] = \app\common\model\user\User::where('is_delete', '=', 0)
                ->where('referee_id', '=', $this->user['user_id'])
                ->count();

            // 待结算条数：区域奖=收货地址匹配（任意买家）；直推奖=直推下级订单（与 getPendingRewards 一致）
            $pendingCountQuery = Db::name('activity_reward_log')
                ->where('user_id', '=', $this->user['user_id'])
                ->whereIn('status', [0]);
            $this->applyPendingRewardBeneficiaryScope($pendingCountQuery, (int)$this->user['user_id']);
            $asset['pending_reward_count'] = (int)$pendingCountQuery->count();

            // 区域代理地址
            $agentProvinceId = (int)($this->user['agent_province_id'] ?? 0);
            $agentCityId     = (int)($this->user['agent_city_id'] ?? 0);
            $agentDistrictId = (int)($this->user['agent_district_id'] ?? 0);
            $agentArea = '';
            if ($agentProvinceId > 0) {
                $agentArea = (string)Db::name('region')->where('id', '=', $agentProvinceId)->value('name');
            }
            if ($agentCityId > 0) {
                $agentArea .= (string)Db::name('region')->where('id', '=', $agentCityId)->value('name');
            }
            if ($agentDistrictId > 0) {
                $agentArea .= (string)Db::name('region')->where('id', '=', $agentDistrictId)->value('name');
            }
            $asset['agent_area'] = $agentArea;

            // 个人业绩：优品区支付成功计入，其余确认收货计入（与奖励发放口径一致）
            $asset['personal_performance'] = WzPerformanceService::sumUserPerformance([(int)$this->user['user_id']]);

            // 团队业绩：伞下所有层级成员（不含自己），口径同上
            $lowerIds = [];
            Product::display_all_all($this->user['user_id'], $lowerIds, 0);
            $asset['team_performance'] = WzPerformanceService::sumUserPerformance($lowerIds);

            unset($asset['points']);

            if (\app\common\service\greenpoints\GreenPointsService::supportsSchema()) {
                $gp = (new \app\common\service\greenpoints\GreenPointsService())->getUserBalances((int)$this->user['user_id']);
                $asset = array_merge($asset, $gp);
                $asset['green_points_exchange'] = \app\common\service\greenpoints\GreenPointsService::getExchangeConfig();
            }

            return $this->renderSuccess('', compact('asset'));
        } catch (\Throwable $e) {
            return $this->renderError($e->getMessage() ?: '获取资产信息失败');
        }
    }

    /**
     * 余额转赠（输入手机号）
     */
    public function transferPoints()
    {
        $model  = \app\api\model\user\User::detail($this->user['user_id']);
        $result = $model->transferBalance($this->request->post());
        if ($result !== false) {
            return $this->renderSuccess('余额转赠成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 申请提现
     */
    public function applyWithdraw()
    {
        $param = $this->request->post();
        $amount = isset($param['amount']) ? (string)$param['amount'] : '0';
        $withdrawType = isset($param['withdraw_type']) ? (int)$param['withdraw_type'] : 0;

        if (!in_array($withdrawType, [1, 2])) {
            return $this->renderError('提现类型参数错误');
        }
        if (bccomp($amount, '0', 2) <= 0) {
            return $this->renderError('提现金额必须大于0');
        }

        $withdrawCfg = \app\common\service\settings\WithdrawalConfigService::get();
        $minAmount  = $withdrawCfg['min_amount'];
        $feePercent = $withdrawCfg['service_fee_percent'];

        if (bccomp($amount, $minAmount, 2) < 0) {
            return $this->renderError('提现金额不能低于' . $minAmount . '元');
        }

        $user = \app\common\model\user\User::detail($this->user['user_id']);
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        // 验证资产充足
        if ($withdrawType == 1) {
            if (bccomp((string)$user['balance'], $amount, 2) < 0) {
                return $this->renderError('余额不足');
            }
        } else {
            if (bccomp((string)$user['points'], $amount, 2) < 0) {
                return $this->renderError('积分不足');
            }
        }

        // 计算手续费
        $fee = bcmul($amount, bcdiv($feePercent, '100', 6), 2);
        $actualAmount = bcsub($amount, $fee, 2);

        Db::startTrans();
        try {
            // 冻结用户资产
            if ($withdrawType == 1) {
                $user->where('user_id', '=', $user['user_id'])->dec('balance', (float)$amount)->update();
                BalanceLogModel::add(
                    BalanceLogSceneEnum::WITHDRAW,
                    [
                        'user_id' => $user['user_id'],
                        'money' => -(float)$amount,
                    ],
                    ['申请提现' . $amount . '元，手续费' . $fee . '元']
                );
            } else {
                $user->setIncPoints(-(float)$amount, '申请提现' . $amount . '积分，手续费' . $fee, false);
            }

            // 创建提现记录
            WithdrawalRecord::create([
                'user_id' => $user['user_id'],
                'amount' => $amount,
                'fee' => $fee,
                'actual_amount' => $actualAmount,
                'withdraw_type' => $withdrawType,
                'status' => 0,
                'app_id' => $this->app_id,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->renderError($e->getMessage() ?: '提现申请失败');
        }

        return $this->renderSuccess('提现申请已提交，请等待审核');
    }

    /**
     * 提现记录列表
     */
    public function withdrawList()
    {
        $param = $this->request->param();
        $list = WithdrawalRecord::where('user_id', '=', $this->user['user_id'])
            ->order('record_id desc')
            ->append(['status_text', 'withdraw_type_text'])
            ->paginate($param);

        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 余额与积分互换
     * direction: balance_to_points | points_to_balance
     * amount: 转换数量
     * 比例由 cloud_ratio.ratio_id=20 控制，num 为百分比，默认100即1:1
     */
    public function exchangePoints()
    {
        $param = $this->request->post();
        $direction = $param['direction'] ?? '';
        $amount = isset($param['amount']) ? round((float)$param['amount'], 2) : 0;

        if (!in_array($direction, ['balance_to_points', 'points_to_balance'])) {
            return $this->renderError('不支持的转换方向');
        }
        if ($amount <= 0) {
            return $this->renderError('转换数量必须大于0');
        }

        // 读取互换比例（ratio_id=20，num为百分比，默认100即1:1）
        $ratioNum = (float)Db::name('cloud_ratio')->where('ratio_id', '=', 20)->where('status', '=', 0)->value('num');
        if ($ratioNum <= 0) {
            $ratioNum = 100;
        }
        $ratio = $ratioNum / 100;

        $userId = $this->user['user_id'];

        Db::startTrans();
        try {
            $user = \app\common\model\user\User::detail($userId);
            if (!$user) {
                throw new \RuntimeException('用户不存在');
            }

            if ($direction === 'balance_to_points') {
                if ((float)$user['balance'] < $amount) {
                    throw new \RuntimeException('余额不足');
                }
                $pointsAmount = round($amount * $ratio, 2);
                $user->where('user_id', '=', $userId)->dec('balance', $amount)->update();
                BalanceLogModel::add(
                    BalanceLogSceneEnum::BALANCE_TO_POINTS,
                    ['user_id' => $userId, 'money' => -$amount],
                    ['转换' . $amount . '余额为' . $pointsAmount . '积分']
                );
                $user->setIncPoints($pointsAmount, '余额转积分，转换' . $amount, false);
            } else {
                if ((float)$user['points'] < $amount) {
                    throw new \RuntimeException('积分不足');
                }
                $balanceAmount = round($amount * $ratio, 2);
                $user->setIncPoints(-$amount, '积分转余额，转换' . $amount, false);
                $user->where('user_id', '=', $userId)->inc('balance', $balanceAmount)->update();
                BalanceLogModel::add(
                    BalanceLogSceneEnum::POINTS_TO_BALANCE,
                    ['user_id' => $userId, 'money' => $balanceAmount],
                    ['转换' . $amount . '积分为' . $balanceAmount . '余额']
                );
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->renderError($e->getMessage() ?: '操作失败');
        }

        $service = new ActivityRewardService();
        $asset = $service->getUserAsset($userId);
        return $this->renderSuccess('操作成功', compact('asset'));
    }


    //团队
    public function getTeam1()
    {
        $param = $this->request->post();

        $lower = [];
        Product::display_all($this->user['user_id'], $lower, 0);
        $num = count($lower);

        $list = \app\common\model\user\User::where(['referee_id' => $this->user['user_id']])->field('user_id,mobile,nickName')->paginate($param);

        return $this->renderSuccess('', compact('list', 'num'));
    }


    //转出余额
    public function transferAdd()
    {
        $param = $this->request->post();

        $mobileCache = Cache::get($this->user['user_id'] . 'hz');
        if($mobileCache){
            return $this->renderError('请勿频繁点击');
        }
        Cache::set($this->user['user_id'] . 'hz',1, 4);

//        if (!isset($param['code']) || empty($param['code'])) {
//            return $this->renderError('请输入短信验证码');
//        }
//
//        if($param['mobile'] != $this->user['mobile']){
//            return $this->renderError('请输入当前登录账号的手机号！');
//        }
//
//        $model = new \app\api\model\user\UserOpen();
//        if(!$model->smsVerify($param)){
//            return $this->renderError('验证失败');
//        }

        if (!isset($param['old_mobile']) || empty($param['old_mobile'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['balance']) || empty($param['balance'])) {
            return $this->renderError('缺少参数');
        }

        $payPassword = trim((string)($param['pay_password'] ?? ''));
        $payPasswordError = User::getPayPasswordVerifyError($this->user, $payPassword);
        if ($payPasswordError !== null) {
            return $this->renderError($payPasswordError);
        }

        $param['balance'] = (int)$param['balance'];
        if ((int)$param['balance'] <= 0) {
            return $this->renderError('输入数额有误');
        }
        if ($this->user['balance'] < $param['balance']) {
            return $this->renderError('余额不足');
        }
        $heUser = \app\common\model\user\User::where(['mobile' => $param['old_mobile']])->where('is_delete', '=', '0')->find();
        if (empty($heUser)) {
            return $this->renderError('找不到该用户');
        }

        if($heUser['referee_id'] == $this->user['user_id'] || $heUser['user_id'] == $this->user['referee_id']){
            self::billAdd($this->user['user_id'], 'dec', 'balance', 1, $param['balance'], '转出至' . $param['old_mobile']);
            self::billAdd($heUser['user_id'], 'inc', 'balance', 1, $param['balance'], '转入来自' . $this->user['mobile']);

            return $this->renderSuccess('转出成功');
        }

        if (!User::isSameReferralLine((int)$this->user['user_id'], (int)$heUser['user_id'])) {
            return $this->renderError('非同一关系线用户不能互转');
        }

        self::billAdd($this->user['user_id'], 'dec', 'balance', 1, $param['balance'], '转出至' . $param['old_mobile']);
        self::billAdd($heUser['user_id'], 'inc', 'balance', 1, $param['balance'], '转入来自' . $this->user['mobile']);

        return $this->renderSuccess('转出成功');
    }

    //直推关系递归到上层的邀请人为止
    public static function display_up_up($one, &$user, $num, $two)
    {
        if ($num > 220800) {
            throw new BaseException(['msg' => "3系统错误，请联系管理员$one"]);
        }
        $num = $num + 1;

        $meUser = User::where(['user_id' => $two])->find();
        if(empty($meUser['referee_id'])){
            $user = [];
            return;
        }
        $list = User::where(['user_id' => $meUser['referee_id']])->find();
        if (empty($list) || $list['user_id'] == $one) {
            $user = $list;
            return;
        }
        self::display_up_up($one, $user, $num, $list['user_id']);

    }

    //查看资讯
    public function getInformation()
    {
        $list = (new \app\common\model\order\CloudInformation())
            ->getVisibleList($this->app_id);
        $first = $list->first();

        // content 保留旧版结构，避免已发布 H5 的资讯详情页失效。
        $content = [
            'help' => $first ? $first['content'] : '',
            'help_prompt' => $first ? $first['help_prompt'] : '',
        ];
        return $this->renderSuccess('操作成功', compact('list', 'content'));
    }

    public function getUserName()
    {
        $param = $this->request->post();

        $user = User::where(['mobile' => $param['mobile']])->where(['is_delete' => 0])->find();

        if(empty($user)){
            return $this->renderError('找不到该用户');
        }

        $test = '是否确认给昵称：' . $user['nickName'] . ',手机号为：' .$param['mobile'] . '的用户转出：' . $param['num'];

        return $this->renderSuccess($user['nickName']);
    }


    //取消提现
    public function cardApplyDel()
    {
        $param = $this->request->post();

        if (!isset($param['card_apply_id']) || empty($param['card_apply_id'])) {
            return $this->renderError('缺少参数');
        }

        $apply = CloudCardApply::where(['card_apply_id' => $param['card_apply_id']])->find();
        if (empty($apply)) {
            return $this->renderError('申请不存在');
        }
        if ($apply['status'] != 1) {
            return $this->renderError('申请已审核');
        }

        $ratioF = CloudRatio::where(['ratio_id' => 6])->find();
        $ratioFive = $ratioF['num'];

        //增加数据 添加记录
        Order::billAdd($this->user['user_id'],'inc', 'balance', 1, $apply['num'], '提现申请取消');
        Order::billAdd($apply['user_id'], 'inc', 'balance', 1, $apply['num'] * ($ratioFive / 100), '提现审核驳回手续费反还');

        CloudCardApply::where(['card_apply_id' => $param['card_apply_id']])->update([
            'status' => 4
        ]);

        return $this->renderSuccess('取消成功');
    }

    //提现申请列表
    public function cardApplyList()
    {
        $param = $this->request->post();
        $list = WithdrawalRecord::where('user_id', '=', $this->user['user_id'])
            ->order('record_id desc')
            ->append(['status_text', 'withdraw_type_text'])
            ->paginate($param);
        return $this->renderSuccess('', compact('list'));

      
//        $param = $this->request->post();
//
//        $list = CloudCardApply::where(['user_id' => $this->user['user_id']])
//            ->order('card_apply_id desc')
//            ->paginate($param);
//
//        return $this->renderSuccess('', compact('list'));
    }

    //银行卡添加
    public function cardAdd()
    {
        $param = $this->request->post();

        if (!isset($param['name']) || empty($param['name'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['card']) || empty($param['card'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['bank']) || empty($param['bank'])) {
            return $this->renderError('缺少参数');
        }

        CloudUserCard::create([
            'user_id' => $this->user['user_id'],
            'name' => $param['name'],
            'card' => $param['card'],
            'bank' => $param['bank'],
        ]);

        return $this->renderSuccess('添加成功');
    }

    //银行卡信息修改
    public function cardUp()
    {
        $param = $this->request->post();

        if (!isset($param['card_id']) || empty($param['card_id'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['name']) || empty($param['name'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['card']) || empty($param['card'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['bank']) || empty($param['bank'])) {
            return $this->renderError('缺少参数');
        }

        CloudUserCard::where(['card_id' => $param['card_id']])->update([
            'name' => $param['name'],
            'card' => $param['card'],
            'bank' => $param['bank'],
        ]);

        return $this->renderSuccess('修改成功');
    }

    //银行卡信息删除
    public function cardDel()
    {
        $param = $this->request->post();

        if (!isset($param['card_id']) || empty($param['card_id'])) {
            return $this->renderError('缺少参数');
        }

        CloudUserCard::where(['card_id' => $param['card_id']])->delete();

        return $this->renderSuccess('删除成功');
    }


    //银行卡列表
    public function cardList()
    {
        $param = $this->request->post();

        $list = CloudUserCard::where(['user_id' => $this->user['user_id']])
            ->order('card_id desc')
            ->paginate($param);

        return $this->renderSuccess('', compact('list'));
    }

    //提现申请
    public function cardApply()
    {
        $param = $this->request->post();
        if($this->user['disabled_level'] > 0){
            return $this->renderError('维护中');
        }

//        if (!isset($param['code']) || empty($param['code'])) {
//            return $this->renderError('请输入短信验证码');
//        }
//
//        $model = new \app\api\model\user\UserOpen();
//        if(!$model->smsVerify($param)){
//            return $this->renderError('验证失败');
//        }

        if (!isset($param['num']) || empty($param['num'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['name']) || empty($param['name'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['card']) || empty($param['card'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['bank']) || empty($param['bank'])) {
            return $this->renderError('缺少参数');
        }
        if ($param['num'] < 100) {
            return $this->renderError('最少提现100');
        }

        //查询比例

        if ($param['num'] > $this->user['balance']) {
            return $this->renderError('金额不足');
        }


//        $allNum = CloudCardApply::where(['user_id' => $this->user['user_id']])->whereTime('create_time', 'today')->sum('num');
//        if (($param['num'] + $allNum) > $ratio['num']) {
//            return $this->renderError('每日兑金额不得超过' . $ratio['num']);
//        }
//        $allCount = CloudCardApply::where(['user_id' => $this->user['user_id']])->where('status', '<>', 4)->whereTime('create_time', 'today')->count();
//        if ($allCount > 0) {
//            return $this->renderError('每日兑金次数一次');
//        }

        CloudCardApply::create([
            'user_id' => $this->user['user_id'],
            'num' => $param['num'],
            'name' => $param['name'],
            'card' => $param['card'],
            'bank' => $param['bank'],
        ]);
        $ratioF = CloudRatio::where(['ratio_id' => 3])->find();
        $ratioFive = $ratioF['num'];
        //减少数据 添加记录
        Order::billAdd($this->user['user_id'],'dec', 'balance', 1, $param['num'], '兑金申请');
        Order::billAdd($this->user['user_id'], 'dec', 'balance', 1, $param['num'] * ($ratioFive / 100), '兑金申请手续费');


        return $this->renderSuccess('申请成功，请等待审核');
    }

    //邀请链接
    public function getPLink()
    {
        $url = base_url().'h5/pages/login/weblogin?app_id=10001&referee_id='.$this->user['user_id'];

        return $this->renderSuccess('', $url);
    }

    //团队
    public function getTeam()
    {
        $param = $this->request->post();

        // 获取所有下级(基于referee_id)
        $lower = [];
        Product::display_all_all($this->user['user_id'], $lower, 0);
        $num = count($lower);
        $currentUserId = (int)$this->user['user_id'];

        // 查询直推团队(仅基于referee_id)
        $list = \app\common\model\user\User::where('is_delete', '=', 0)
            ->where('referee_id', '=', $currentUserId)
            ->field('user_id,mobile,nickName,avatarUrl')
            ->paginate($param)
            ->each(function ($item) {
                $eachLower = [];
                Product::display_all_all($item['user_id'], $eachLower, 0);
                $lowerTeamNum = count($eachLower);
                $lowerTeamPrice = WzPerformanceService::sumUserPerformance($eachLower);
                $lowerPrice = WzPerformanceService::sumUserPerformance([(int)$item['user_id']]);

                $item['lower_team_num'] = $lowerTeamNum;
                $item['lower_team_price'] = $lowerTeamPrice;
                $item['lower_price'] = $lowerPrice;
                return $item;
            });

        // 获取上级信息(基于referee_id)
        $parentUserId = (int)($this->user['referee_id'] ?? 0);
        $pUser = $parentUserId > 0 ? User::where(['user_id' => $parentUserId])->find() : null;
        $p_mobile = empty($pUser) ? '无' : $pUser['mobile'];
        $p_name = empty($pUser) ? '无' : $pUser['nickName'];
        
        // 计算团队总业绩
        $total_team = 0;
        foreach ($list as $item) {
            $total_team += $item['lower_price'];
            $total_team += $item['lower_team_price'];
        }

        // 团队总/优品/消费/今日新增业绩（基于全部伞下用户；口径与奖励发放一致）
        $lowerAndSelf = array_merge($lower, [$currentUserId]);
        $total_team_price = WzPerformanceService::sumUserPerformance($lowerAndSelf);
        $total_first_price = WzPerformanceService::sumUserPerformance($lowerAndSelf, WzPerformanceService::ZONE_FIRST);
        $total_rebuy_price = WzPerformanceService::sumUserPerformance($lowerAndSelf, ActivityRewardService::ZONE_REBUY);
        $todayStart = strtotime(date('Y-m-d'));
        $todayEnd   = $todayStart + 86400 - 1;
        $today_new_price = WzPerformanceService::sumUserPerformance($lowerAndSelf, null, $todayStart, $todayEnd);

        // 伞下全部人数(基于referee_id)
        $team_num = \app\common\model\user\User::where('is_delete', '=', 0)
            ->where('referee_id', '=', $currentUserId)
            ->count();

        return $this->renderSuccess('', compact('list', 'num', 'p_name', 'p_mobile', 'team_num', 'total_team', 'total_team_price', 'total_first_price', 'total_rebuy_price', 'today_new_price'));
    }

    /**
     * 能量记录列表（按 status 筛选：0全部 1待释放 2释放中 3已释放）
     */
    public function getEnergyRecordList()
    {
        $energyService = new \app\common\service\activity\EnergyRewardService();

        $status   = (int)$this->request->param('status', 0);
        $page     = max(1, (int)$this->request->param('page', 1));
        $pageSize = min(100, max(1, (int)$this->request->param('page_size', 20)));

        if (!in_array($status, [0, 1, 2, 3], true)) {
            return $this->renderError('status 无效，可选 0全部 1待释放 2释放中 3已释放');
        }

        $result = $energyService->getEnergyRecordList((int)$this->user['user_id'], $status, $page, $pageSize);
        return $this->renderSuccess('', $result);
    }

    /**
     * 能量直推解锁明细（当前用户作为获益人）
     */
    public function getEnergyUnlockList()
    {
        $energyService = new \app\common\service\activity\EnergyRewardService();
        if (!$energyService->isEnabled() || !$energyService->supportsSchema()) {
            return $this->renderSuccess('', ['list' => [], 'total' => 0]);
        }

        $page     = max(1, (int)$this->request->get('page', 1));
        $pageSize = min(100, max(1, (int)$this->request->get('page_size', 20)));

        $query = Db::name('energy_unlock_log')
            ->alias('l')
            ->leftJoin('user u', 'l.from_user_id = u.user_id')
            ->where('l.user_id', '=', $this->user['user_id'])
            ->where('l.status', '=', 1)
            ->field([
                'l.log_id',
                'l.record_id',
                'l.from_user_id',
                'l.from_order_id',
                'l.unlock_seq',
                'l.release_ratio',
                'l.release_amount',
                'l.settle_amount',
                'l.create_time',
                'u.nickName as from_nick_name',
                'u.mobile as from_mobile',
            ])
            ->order('l.log_id', 'desc');

        $total = (int)$query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();
        foreach ($list as &$row) {
            $row['create_time_text'] = $row['create_time'] ? date('Y-m-d H:i:s', (int)$row['create_time']) : '';
            $row['release_ratio_text'] = ((int)$row['unlock_seq'] % 2 === 1) ? '50%' : '100%';
            $row['is_settled'] = bccomp((string)($row['settle_amount'] ?? '0'), '0', 2) > 0 ? 1 : 0;
        }

        return $this->renderSuccess('', compact('list', 'total'));
    }

    /**
     * 待结算奖励明细：直推奖来自直推下级订单；省/市/区代奖来自收货地址匹配（任意买家）
     */
    public function getPendingRewards()
    {
        $page     = max(1, (int)$this->request->get('page', 1));
        $pageSize = min(100, max(1, (int)$this->request->get('page_size', 20)));

        $query = Db::name('activity_reward_log')
            ->alias('r')
            ->join('order o', 'r.order_id = o.order_id', 'LEFT')
            ->leftJoin('user fu', 'r.from_user_id = fu.user_id')
            ->where('r.user_id', '=', $this->user['user_id'])
            ->whereIn('r.status', [0]);
        $this->applyPendingRewardBeneficiaryScope($query, (int)$this->user['user_id'], 'r');
        $query
            ->field([
                'r.log_id',
                'r.user_id',
                'r.from_user_id',
                'r.order_id',
                'r.scene',
                'r.amount',
                'r.asset_type',
                'r.reward_month',
                'r.zone_type',
                'r.status',
                'r.remark',
                'r.create_time',
                'o.order_no',
                'o.pay_price',
                'o.pay_time',
                'fu.nickName as from_nick_name',
                'fu.mobile as from_mobile',
            ])
            ->order('r.create_time', 'desc');

        $total = $query->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        // 格式化数据
        $statusTextMap = [0 => '待结算', 2 => '已撤销'];
        foreach ($list as &$item) {
            $item['scene_text']       = (string)($item['remark'] ?? '');
            $nickName                 = trim((string)($item['from_nick_name'] ?? ''));
            $mobile                   = trim((string)($item['from_mobile'] ?? ''));
            $item['from_user_name']   = $nickName !== '' ? $nickName : ($mobile !== '' ? $mobile : ('用户' . (int)$item['from_user_id']));
            $zoneTextMap = [1 => '品牌优选区', 2 => '惠民区', 3 => '代理进货区', 4 => '合伙人'];
            $item['zone_type_text']   = $zoneTextMap[(int)$item['zone_type']] ?? '-';
            $assetType = (string)($item['asset_type'] ?? '');
            $item['asset_type_text']  = $assetType === 'green_points' ? '绿色积分'
                : ($assetType === 'balance' ? '余额'
                : ($assetType === 'points' ? '积分' : '余额+积分'));
            $item['status_text']      = $statusTextMap[$item['status']] ?? '未知';
            $item['create_time_text'] = date('Y-m-d H:i:s', $item['create_time']);
            $item['pay_time_text']    = $item['pay_time'] ? date('Y-m-d H:i:s', $item['pay_time']) : '';
        }

        return $this->renderSuccess('', [
            'list'  => $list,
            'total' => $total,
        ]);
    }


    function payStatus()
    {
        $data = $this->request->param(); 
        $order_id = $data['order_id'];
        $order = \app\common\model\order\Order::where('order_id', $order_id)->find();
        if (empty($order)) {
          return $this->renderError('订单不存在');
        }
        return $this->renderSuccess('', ['pay_status' => $order['pay_status']]);
    }

    /** 消费区省/市/区代待结算 scene */
    private const PENDING_REGION_SCENES = ['region_province', 'region_city', 'region_district'];

    /** 买家本人待结算（绿色积分等） */
    private const PENDING_SELF_SCENES = ['green_points_gift'];

    /**
     * 待结算可见范围：区域奖不限直推关系；直推奖等其余 scene 仅直推下级订单
     *
     * @param \think\db\Query $query
     */
    private function applyPendingRewardBeneficiaryScope($query, int $userId, string $alias = ''): void
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $directLowerIds = Db::name('user')
            ->where('referee_id', '=', $userId)
            ->where('is_delete', '=', 0)
            ->column('user_id');

        $query->where(function ($q) use ($directLowerIds, $prefix, $userId) {
            $q->whereIn($prefix . 'scene', self::PENDING_REGION_SCENES);
            $q->whereOr(function ($sub) use ($prefix, $userId) {
                $sub->whereIn($prefix . 'scene', self::PENDING_SELF_SCENES)
                    ->where($prefix . 'user_id', '=', $userId);
            });
            if (!empty($directLowerIds)) {
                $q->whereOr($prefix . 'from_user_id', 'in', $directLowerIds);
            }
        });
    }


}
