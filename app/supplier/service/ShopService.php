<?php

namespace app\supplier\service;

use app\common\service\statistics\ProductRankingService;
use app\supplier\model\order\OrderRefund;
use app\supplier\model\product\Product;
use app\supplier\model\order\Order;
use app\supplier\model\supplier\Supplier as SupplierModel;
use app\supplier\model\user\Favorite as FavoriteModel;
use app\supplier\model\user\Visit as VisitModel;

/**
 * 商城模型
 */
class ShopService
{
    // 商品模型
    private $ProductModel;
    // 订单模型
    private $OrderModel;
    // 订单退款模型
    private $OrderRefund;
    // 收藏模型
    private $FavoriteModel;
    // 商户id
    private $shop_supplier_id;

    /**
     * 构造方法
     */
    public function __construct($shop_supplier_id)
    {
        /* 初始化模型 */
        $this->ProductModel = new Product();
        $this->OrderModel = new Order();
        $this->OrderRefund = new OrderRefund();
        $this->FavoriteModel = new FavoriteModel();
        $this->shop_supplier_id = $shop_supplier_id;
    }

    /**
     * 后台首页数据
     */
    public function getHomeData($param)
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $supplier = SupplierModel::detail($this->shop_supplier_id);
        $data = [
            'top_data' => [
                // 商品总量
                'product_total' => $this->getProductTotal(),
                // 商品今日总量
                'product_today' => $this->getProductTotal($today),
                // 商品昨日总量
                'product_yesterday' => $this->getProductTotal($yesterday),
                // 订单总量
                'order_total' => $this->getOrderTotal(),
                // 订单今日总量
                'order_today' => $this->getOrderTotal($today),
                // 订单昨日总量
                'order_yesterday' => $this->getOrderTotal($yesterday),
                // 订单销售额
                'total_money' => $this->getOrderTotalPrice(),
                // 订单今日销售额
                'total_money_today' => $this->getOrderTotalPrice($today),
                // 订单昨日销售额
                'total_money_yesterday' => $this->getOrderTotalPrice($yesterday),
                // 店铺关注人数
                'fav_count' => $supplier['fav_count'],
                // 店铺今日关注人数
                'fav_count_today' => $this->getFavUserTotal($today),
                // 店铺昨日关注人数
                'fav_count_yesterday' => $this->getFavUserTotal($yesterday),
                // 配送评分
                'express_score' => $supplier['express_score'],
                // 服务评分
                'server_score' => $supplier['server_score'],
                // 描述评分
                'describe_score' => $supplier['describe_score'],
            ],
            'wait_data' => [
                //订单
                'order' => [
                    'delivery' => $this->getReviewOrderTotal(),
                    'refund' => $this->getRefundOrderTotal(),
                ],
                //商品
                'product' => [
                    'audit' => $this->getProductAuditTotal(),
                ],
                //库存
                'stock' => [
                    'product' => $this->getProductStockTotal(),
                ],
            ]
        ];
        //数据升降比例
        $data['top_data']['product_rate'] = $data['top_data']['product_yesterday'] > 0 ? round(($data['top_data']['product_today'] - $data['top_data']['product_yesterday']) / $data['top_data']['product_yesterday'] * 100, 2) : round($data['top_data']['product_today'] * 100, 2);
        $data['top_data']['total_money_rate'] = $data['top_data']['total_money_yesterday'] > 0 ? round(($data['top_data']['total_money_today'] - $data['top_data']['total_money_yesterday']) / $data['top_data']['total_money_yesterday'] * 100, 2) : round($data['top_data']['total_money_today'] * 100, 2);
        $data['top_data']['order_rate'] = $data['top_data']['order_yesterday'] > 0 ? round(($data['top_data']['order_today'] - $data['top_data']['order_yesterday']) / $data['top_data']['order_yesterday'] * 100, 2) : round($data['top_data']['order_today'] * 100, 2);
        $data['top_data']['fav_count_rate'] = $data['top_data']['fav_count_yesterday'] > 0 ? round(($data['top_data']['fav_count_today'] - $data['top_data']['fav_count_yesterday']) / $data['top_data']['fav_count_yesterday'] * 100, 2) : round($data['top_data']['fav_count_today'] * 100, 2);
        //商品销售排行
        $data['productRank'] = (new ProductRankingService())->getSaleTimeRanking($param, $this->shop_supplier_id);
        //销售额概况
        $data['saleData'] = $this->getSaleByDate($param['sale_time']);
        //访客数据
        $data['userData'] = $this->getUserByDate($param['user_time']);
        //数据更新时间
        $data['update_time'] = date('Y-m-d H:i:s');
        return $data;
    }

    /**
     * 通过时间段查询订单数据
     */
    private function getUserByDate($days)
    {
        $dateInfo = $this->getDays($days);
        $days = $dateInfo['date'];
        $data = [];
        foreach ($days as $day) {
            $data[] = [
                'day' => $day,
                'visit_num' => (new VisitModel())->getVisitUserData($day, null, $this->shop_supplier_id),//访客数
                'total_num' => (new VisitModel())->getVisitData($day, null, $this->shop_supplier_id),//访客量
            ];
        }
        $detail['data'] = $data;
        $detail['days'] = $dateInfo['time'];
        return $detail;
    }

    /**
     * 通过时间段查询订单数据
     */
    private function getSaleByDate($days)
    {
        $dateInfo = $this->getDays($days);
        $days = $dateInfo['date'];
        $data = [];
        $endTime = null;
        foreach ($days as $day) {
            $data[] = [
                'day' => $day,
                'total_money' => $this->OrderModel->getOrderData($day, null, 'order_total_price', $this->shop_supplier_id),
            ];
            $endTime = $day;
        }
        $startTime = $days[0];
        $detail['data'] = $data;
        $detail['days'] = $dateInfo['time'];
        $detail['saleMoney'] = $this->OrderModel->getOrderData($startTime, $endTime, 'order_total_price', $this->shop_supplier_id);
        return $detail;
    }

    /**
     * 获取具体日期数组
     */
    private function getDays($time_type = '')
    {
        //搜索时间段
        if (!$time_type) {
            //没有传，则默认为最近7天
            $end_time = date('Y-m-d', time());
            $start_time = date('Y-m-d', strtotime('-7 day', time()));
        } else {
            if ($time_type == 1) {//近7天
                $end_time = date('Y-m-d', time());
                $start_time = date('Y-m-d', strtotime('-7 day', time()));
            } elseif ($time_type == 2) {//近15天
                $end_time = date('Y-m-d', time());
                $start_time = date('Y-m-d', strtotime('-15 day', time()));
            } else {//近30天
                $end_time = date('Y-m-d', time());
                $start_time = date('Y-m-d', strtotime('-30 day', time()));
            }
        }
        $dt_start = strtotime($start_time);
        $dt_end = strtotime($end_time);
        $date = [];
        $time = [];
        $date[] = date('Y-m-d', strtotime($start_time));
        $time[] = date('m-d', strtotime($start_time));
        while ($dt_start < $dt_end) {
            $date[] = date('Y-m-d', strtotime('+1 day', $dt_start));
            $time[] = date('m-d', strtotime('+1 day', $dt_start));
            $dt_start = strtotime('+1 day', $dt_start);
        }
        $data['date'] = $date;
        $data['time'] = $time;
        return $data;
    }

    /**
     * 获取商品总量
     */
    private function getProductTotal($day = '')
    {
        return number_format($this->ProductModel->getProductDateTotal($this->shop_supplier_id, $day));
    }

    /**
     * 获取商品总量
     */
    private function getProductAuditTotal()
    {
        return number_format($this->ProductModel->getProductTotal([
            'shop_supplier_id' => $this->shop_supplier_id,
            'audit_status' => 0
        ]));
    }

    /**
     * 获取商品库存告急总量
     */
    private function getProductStockTotal()
    {
        return number_format($this->ProductModel->getProductStockTotal($this->shop_supplier_id));
    }

    /**
     * 获取订单总量
     */
    private function getOrderTotal($day = null)
    {
        return number_format($this->OrderModel->getOrderData($day, null, 'order_total', $this->shop_supplier_id));
    }

    /**
     * 获取待处理订单总量
     */
    private function getReviewOrderTotal()
    {
        return number_format($this->OrderModel->getReviewOrderTotal($this->shop_supplier_id));
    }

    /**
     * 获取售后订单总量
     */
    private function getRefundOrderTotal()
    {
        return number_format($this->OrderRefund->getRefundOrderTotal($this->shop_supplier_id));
    }

    /**
     * 获取某天的总销售额
     */
    private function getOrderTotalPrice($day = null)
    {
        return sprintf('%.2f', $this->OrderModel->getOrderTotalPrice($day, null, $this->shop_supplier_id));
    }

    /**
     * 店铺总销售额
     */
    private function getOrderTotalMoney($day = null)
    {
        return sprintf('%.2f', $this->OrderModel->getOrderTotalPrice(null, $day, $this->shop_supplier_id));
    }

    /**
     * 获取某天的下单用户数
     */
    private function getPayOrderUserTotal($day)
    {
        return number_format($this->OrderModel->getPayOrderUserTotal($day, $this->shop_supplier_id));
    }

    /**
     * 获取某天的关注用户数
     */
    private function getFavUserTotal($day)
    {
        return number_format($this->FavoriteModel->getUserTotal($day, $this->shop_supplier_id));
    }
}