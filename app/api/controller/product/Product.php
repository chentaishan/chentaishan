<?php

namespace app\api\controller\product;

use app\api\controller\user\Order;
use app\api\model\plus\chat\ChatRelation as ChatRelationModel;
use app\api\model\product\Product as ProductModel;
use app\api\model\order\Cart as CartModel;
use app\api\controller\Controller;
use app\api\model\settings\Setting as SettingModel;
use app\api\model\shop\FullReduce as FullReduceModel;
use app\api\model\user\Visit as VisitModel;
use app\api\service\common\RecommendService;
use app\common\exception\BaseException;
use app\common\library\helper;
use app\common\model\order\CloudAuctionRecord;
use app\common\model\order\CloudCollUser;
use app\common\model\order\CloudRatio;
use app\common\model\user\User;
use app\common\service\activity\WzNormalZoneProductService;
use app\common\service\qrcode\ProductService;
use app\api\model\user\Favorite as FavoriteModel;
use app\api\model\plus\coupon\Coupon as CouponModel;
use app\common\model\supplier\Service as ServiceModel;

/**
 * 商品控制器
 */
class Product extends Controller
{
    /**
     * 商品列表
     */
    public function lists()
    {
        // 整理请求的参数
        $param = array_merge($this->postData(), [
            'product_status' => 10,
            'audit_status' => 10
        ]);

        $zoneType = (int)($param['zone_type'] ?? 0);
        $user = $this->getUser(false);
        if (WzNormalZoneProductService::isAgentStockZone($zoneType)) {
            $accessErr = WzNormalZoneProductService::validateAgentStockZoneAccess($user);
            if ($accessErr !== null) {
                return $this->renderError($accessErr);
            }
        }

        // 获取列表数据
        $model = new ProductModel;
        $list = $model->getList($param, $user);
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 推荐产品
     */
    public function recommendProduct($location)
    {
        $recommend = SettingModel::getItem('recommend');
        $model = new ProductModel;
        $is_recommend = RecommendService::checkRecommend($recommend, $location);
        $list = [];
        if ($is_recommend) {
            $list = $model->getRecommendProduct($recommend);
        }
        return $this->renderSuccess('', compact('list', 'recommend', 'is_recommend'));
    }

    /**
     * 获取商品详情
     */
    public function detail($product_id, $url = '')
    {
        $params = $this->postData();
        // 用户信息
        $user = $this->getUser(false);
        // 商品详情
        $model = new ProductModel;
        $product = $model->getDetails($product_id, $this->getUser(false));
        if ($product === false || $product['audit_status'] != 10 || $product['product_status']['value'] != 10) {
            return $this->renderError($model->getError() ?: '商品信息不存在');
        }
        // 多规格商品sku信息（须在 getDetails 改价之后构建，否则 spec_form.product_price 为原价）
        $skuForSpec = $product['sku'];
        if (is_object($skuForSpec) && method_exists($skuForSpec, 'toArray')) {
            $skuForSpec = $skuForSpec->toArray();
        }
        $specData = $product['spec_type'] == 20 ? $model->getManySpecData($product['spec_rel'], $skuForSpec) : null;
        $isfollow = 0;
        if ($user) {
            if (FavoriteModel::detail($product_id, 20, $user['user_id'])) {
                $isfollow = 1;
            }
        }
        $product['isfollow'] = $isfollow;
        $dataCoupon['shop_supplier_id'] = $product['shop_supplier_id'];
        $model = new CouponModel;
        $couponList = $model->getWaitList($dataCoupon, $this->getUser(false), 1);
        // 访问记录
        (new VisitModel())->addVisit($user, $product['supplier'], $params['visitcode'], $product);
        // 优惠信息
        $discount = [
            // 商品满减
            'product_reduce' => FullReduceModel::getListByProduct($product_id),
            // 赠送积分
            'give_points' => $this->getGivePoints($product),
            // 商品优惠券
            'product_coupon' => $this->getCouponList($product),
        ];
        //是否显示优惠
        $show_discount = false;
        if (count($discount['product_reduce']) > 0
            || $discount['give_points'] > 0
            || count($discount['product_coupon']) > 0) {
            $show_discount = true;
        }
        //返回客服id
        $product['chat_user_id'] = (new ChatRelationModel)->getChatUser($product['shop_supplier_id'], $user);
        //视频链接
        if ($product['video_type'] == 0 && $product['video']) {
            $product['video_link'] = $product['video']['file_path'];
        }
        unset($product['video']);
        //详情视频链接
        if ($product['video_type_detail'] == 0 && $product['contentVideo']) {
            $product['video_link_detail'] = $product['contentVideo']['file_path'];
        }
        unset($product['contentVideo']);
        return $this->renderSuccess('', [
            // 商品详情
            'detail' => $product,
            // 优惠信息
            'discount' => $discount,
            // 显示优惠
            'show_discount' => $show_discount,
            // 购物车商品总数量
            'cart_total_num' => $user ? (new CartModel())->getProductNum($user) : 0,
            // 多规格商品sku信息
            'specData' => $specData,
            // 微信公众号分享参数
            'share' => $this->getShareParams($url, $product['product_name'], $product['product_name'], '/pages/product/detail/detail', $product['image'][0]['file_path']),
            'couponList' => $couponList,
            //是否显示店铺信息
            'store_open' => SettingModel::getStoreOpen(),
            //是否开启客服
            'service_open' => SettingModel::getSysConfig()['service_open'],
            //店铺客服信息
            'mp_service' => ServiceModel::detail($product['shop_supplier_id']),
        ]);
    }

    /**
     * 赠送积分
     */
    private function getGivePoints($product)
    {
        if ($product['is_points_gift'] == 0) {
            return 0;
        }
        // 积分设置
        $setting = SettingModel::getItem('points');
        // 条件：后台开启开启购物送积分
        if (!$setting['is_shopping_gift']) {
            return 0;
        }
        // 积分赠送比例
        $ratio = $setting['gift_ratio'] / 100;
        // 计算赠送积分数量
        return helper::bcmul($product['product_price'], $ratio, 0);
    }

    /**
     * 获取商品可用优惠券
     */
    private function getCouponList($product)
    {
        // 可领取优惠券
        $model = new CouponModel;
        $user = $this->getUser(false);
        $couponList = $model->getList($user, null, true, $product['shop_supplier_id']);
        foreach ($couponList as $item) {
            // 限制商品
            if ($item['apply_range'] == 20) {
                $product_ids = explode(',', $item['product_ids']);
                if (!in_array($product['product_id'], $product_ids)) {
                    unset($item);
                }
            }
        }
        return $couponList;
    }

    /**
     * 预告产品
     */
    public function previewProduct()
    {
        // 整理请求的参数
        $param = array_merge($this->postData(), [
            'type' => 'preview',
        ]);
        // 获取列表数据
        $model = new ProductModel;
        $list = $model->getList($param, $this->getUser(false));
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 生成商品海报
     */
    public function poster($product_id, $source)
    {
        // 商品详情
        $detail = ProductModel::detail($product_id);
        $Qrcode = new ProductService($detail, $this->getUser(false), $source);
        return $this->renderSuccess('', [
            'qrcode' => $Qrcode->getImage(),
        ]);
    }

    public static function timerJobTest()
    {
        $cloud = CloudRatio::select();
        foreach ($cloud as $key => $value) {
            if ($value['ratio_id'] == 1) {
                $v1a = $value['num'];
            }
            if ($value['ratio_id'] == 2) {
                $v1b = $value['num'];
            }
            if ($value['ratio_id'] == 3) {
                $v2a = $value['num'];
            }
            if ($value['ratio_id'] == 4) {
                $v2b = $value['num'];
            }
            if ($value['ratio_id'] == 5) {
                $v3a = $value['num'];
            }
            if ($value['ratio_id'] == 6) {
                $v3b = $value['num'];
            }
            if ($value['ratio_id'] == 7) {
                $v4a = $value['num'];
            }
            if ($value['ratio_id'] == 8) {
                $v4b = $value['num'];
            }
            if ($value['ratio_id'] == 9) {
                $v5a = $value['num'];
            }
            if ($value['ratio_id'] == 10) {
                $v5b = $value['num'];
            }
            if ($value['ratio_id'] == 11) {
                $v1c = $value['num'];
            }
            if ($value['ratio_id'] == 12) {
                $v2c = $value['num'];
            }
            if ($value['ratio_id'] == 13) {
                $v3c = $value['num'];
            }
            if ($value['ratio_id'] == 14) {
                $v4c = $value['num'];
            }
            if ($value['ratio_id'] == 15) {
                $v5c = $value['num'];
            }
        }

        //遍历全部用户
        $allUser = User::where(['is_delete' => 0])->field('user_id,team_grade,referee_id')->select();
        foreach ($allUser as $v){
            $lower = [];
            self::display_all($v['user_id'], $lower, 0);

            //伞下人数是否达到升级要求
            if(count($lower) >= $v1a){
                $lowerBalance = User::whereIn('user_id', $lower)->sum('balance_all_old');
                //伞下人全部业绩是否达到升级要求
                if($lowerBalance >= $v1b){
                    User::where(['user_id' => $v['user_id']])->update([
                        'team_grade' => 1,
                    ]);
                }

            }
            if(count($lower) >= $v2a){
                $lowerBalance = User::whereIn('user_id', $lower)->sum('balance_all_old');
                if($lowerBalance >= $v2b){
                    User::where(['user_id' => $v['user_id']])->update([
                        'team_grade' => 2,
                    ]);
                }
            }
            if(count($lower) >= $v3a){
                $lowerBalance = User::whereIn('user_id', $lower)->sum('balance_all_old');
                if($lowerBalance >= $v3b){
                    User::where(['user_id' => $v['user_id']])->update([
                        'team_grade' => 3,
                    ]);
                }
            }
            if(count($lower) >= $v4a){
                $lowerBalance = User::whereIn('user_id', $lower)->sum('balance_all_old');
                if($lowerBalance >= $v4b){
                    User::where(['user_id' => $v['user_id']])->update([
                        'team_grade' => 4,
                    ]);
                }
            }
            if(count($lower) >= $v5a){
                $lowerBalance = User::whereIn('user_id', $lower)->sum('balance_all_old');
                if($lowerBalance >= $v5b){
                    User::where(['user_id' => $v['user_id']])->update([
                        'team_grade' => 5,
                    ]);
                }
            }

        }

        $upUser = User::where(['is_delete' => 0])->where('team_grade', '>', '0')->field('user_id,team_grade')->select();
        $todayPrice = User::where(['is_delete' => 0])->sum('balance_all');
        $oneUserNum = User::where(['is_delete' => 0])->where('team_grade', '=', '1')->count();
        $towUserNum = User::where(['is_delete' => 0])->where('team_grade', '=', '2')->count();
        $threeUserNum = User::where(['is_delete' => 0])->where('team_grade', '=', '3')->count();
        $fourUserNum = User::where(['is_delete' => 0])->where('team_grade', '=', '4')->count();
        $fiveUserNum = User::where(['is_delete' => 0])->where('team_grade', '=', '5')->count();

        $everyOnePrice = 0;
        $text = '';
        //分红
        foreach ($upUser as $value){
            if($upUser['team_grade'] == 1){
                $everyOnePrice = $todayPrice / $oneUserNum / 100 * $v1c;
                $text = 'v1';
            }
            if($upUser['team_grade'] == 2){
                $everyOnePrice = $todayPrice / $towUserNum / 100 * $v2c;
                $text = 'v2';
            }
            if($upUser['team_grade'] == 3){
                $everyOnePrice = $todayPrice / $threeUserNum / 100 * $v3c;
                $text = 'v3';
            }
            if($upUser['team_grade'] == 4){
                $everyOnePrice = $todayPrice / $fourUserNum / 100 * $v4c;
                $text = 'v4';
            }
            if($upUser['team_grade'] == 5){
                $everyOnePrice = $todayPrice / $fiveUserNum / 100 * $v5c;
                $text = 'v5';
            }

            Order::billAdd($value['user_id'], 'inc', 'balance', 1, $everyOnePrice, $text . '分红');
        }

    }

    //递归下层的全部人id
    public static function display_all_all($userId, &$arr, $num)
    {
        if ($num > 220800) {
            throw new BaseException(['msg' => "系统错误，请联系管理员$userId"]);
        }
        $num = $num + 1;
        $list = \app\common\model\user\User::where(['referee_id' => $userId])->column('user_id');
        if (empty($list)) {
            return;
        }
        foreach ($list as $v) {
            $arr[] = $v;
            self::display_all_all($v, $arr, $num);

        }

    }

    //递归下层的全部人id
    public static function display_all($userId, &$arr, $num)
    {
        if ($num > 220800) {
            throw new BaseException(['msg' => "系统错误，请联系管理员$userId"]);
        }
        $num = $num + 1;
        $list = \app\common\model\user\User::where(['referee_id' => $userId])->column('user_id');
        if (empty($list)) {
            return;
        }
        foreach ($list as $v) {
            $arr[] = $v;
            self::display_all($v, $arr, $num);

        }

    }

    //递归到上层高级别为止
    public static function display_up_com($p_id, &$user, $num, $grade)
    {
        if ($num > 220800) {
            throw new BaseException(['msg' => "3系统错误，请联系管理员$p_id"]);
        }
        $num = $num + 1;

        $pUser = User::where(['user_id' => $p_id])->find();
        if (!empty($pUser)) {
            if($pUser['grade_id'] > $grade){
                $user = $pUser;
                return;
            }
        }else{
            return;
        }
        self::display_up_com($pUser['referee_id'], $user, $num, $grade);

    }

    //递归到上层同级别为止
    public static function display_up_to($p_id, &$user, $num, $grade)
    {
        if ($num > 220800) {
            throw new BaseException(['msg' => "3系统错误，请联系管理员$p_id"]);
        }
        $num = $num + 1;

        $pUser = User::where(['user_id' => $p_id])->find();
        if (!empty($pUser)) {
            if($pUser['job_grade'] == $grade){
                $user = $pUser;
                return;
            }
            if($pUser['job_grade'] > $grade){
                return;
            }
        }else{
            return;
        }

        self::display_up_to($pUser['referee_id'], $user, $num, $grade);

    }


    //计算本区订货总额
    public static function getAllPrice($userId)
    {
        $arr = [];
        self::display_all($userId, $arr, 0);
        array_push($arr, $userId);

        return \app\common\model\order\Order::where(['pay_status' => 20])->whereIn('user_id', $arr)->sum('pay_status');

        //return ['num' => $num, 'price' => $price];
    }

    //计算本区本月份订货总额
    public static function getAllPriceMonth($userId)
    {
        $arr = [];
        self::display_all($userId, $arr, 0);
        array_push($arr, $userId);

        // 获取本月开始和结束的时间戳
        $startTime = strtotime(date('Y-m-01 00:00:00'));  // 本月第一天
        $endTime = strtotime(date('Y-m-t 23:59:59'));     // 本月最后一天

        return \app\common\model\order\Order::where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->where(['pay_status' => 20])
            ->whereIn('user_id', $arr)
            ->sum('pay_status');

        //return ['num' => $num, 'price' => $price];
    }

    public static function yesterdayText()
    {
        //return date("Y-m-d", time());
        return date("Y-m-d", strtotime("-1 day"));
    }

}
