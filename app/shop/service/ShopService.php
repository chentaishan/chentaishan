<?php

namespace app\shop\service;

use app\common\service\statistics\ProductRankingService;
use app\shop\model\order\OrderRefund;
use app\shop\model\product\Product;
use app\shop\model\order\Order;
use app\shop\model\user\User;
use app\shop\model\product\Comment;
use app\shop\model\plus\agent\Cash as AgentCashModel;
use app\shop\model\supplier\Supplier as SupplierModel;
use app\shop\model\plus\agent\Apply as AgentApplyModel;
use app\shop\model\supplier\Apply as SupplierApplyModel;
use app\shop\model\supplier\Cash as SupplierCashModel;
use app\shop\model\supplier\DepositRefund as DepositRefundModel;
use app\shop\model\plus\point\Product as PointProductModel;
use app\shop\model\plus\bargain\Product as BargainProductModel;
use app\shop\model\plus\assemble\Product as AssembleProductModel;
use app\shop\model\plus\seckill\Product as SeckillProductModel;
use app\shop\model\supplier\ServiceApply as ServiceApplyModel;
use app\shop\model\user\Visit as VisitModel;
use app\shop\model\plus\advance\Product as AdvanceProductModel;

/**
 * 商城模型
 */
class ShopService
{
    // 商品模型
    private $ProductModel;
    // 订单模型
    private $OrderModel;
    // 用户模型
    private $UserModel;
    // 订单退款模型
    private $OrderRefund;

    /**
     * 构造方法
     */
    public function __construct()
    {
        /* 初始化模型 */
        $this->ProductModel = new Product();
        $this->OrderModel = new Order();
        $this->UserModel = new User();
        $this->OrderRefund = new OrderRefund();
    }

    /**
     * 后台首页数据
     */
    public function getHomeData($param)
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $data = [
            'top_data' => [
                // 商品总量
                'product_total' => $this->getProductTotal(),
                //商品今日总量
                'product_today' => $this->getProductTotal($today),
                //商品昨日总量
                'product_yesterday' => $this->getProductTotal($yesterday),
                // 用户总量
                'user_total' => $this->getUserTotal(),
                // 用户今日总量
                'user_today' => $this->getUserTotal($today),
                // 用户昨日总量
                'user_yesterday' => $this->getUserTotal($yesterday),
                // 订单总量
                'order_total' => $this->getOrderTotal(),
                // 订单今日总量
                'order_today' => $this->getOrderTotal($today),
                // 订单昨日总量
                'order_yesterday' => $this->getOrderTotal($yesterday),
                // 店铺总量
                'supplier_total' => $this->getSupplierTotal(),
                // 店铺今日总量
                'supplier_today' => $this->getSupplierTotal($today),
                // 店铺昨日总量
                'supplier_yesterday' => $this->getSupplierTotal($yesterday)
            ],
            'wait_data' => [
                //订单
                'order' => [
                    'disposal' => $this->getReviewOrderTotal(),
                    'refund' => $this->getRefundOrderTotal(),
                    'plate' => $this->getPlateOrderTotal(),
                ],
                //分销商
                'agent' => [
                    'cash_apply' => $this->getAgentApplyTotal(10),
                    'apply' => AgentApplyModel::getApplyCount(),
                    'cash_money' => $this->getAgentApplyTotal(20),
                ],
                //供应商
                'supplier' => [
                    'apply' => SupplierApplyModel::getApplyCount(),
                    'cash_apply' => SupplierCashModel::getApplyCount(10),
                    'cash_money' => SupplierCashModel::getApplyCount(20),
                    'refund' => DepositRefundModel::getRefundCount(),
                    'service' => ServiceApplyModel::getApplyCount(),
                ],
                //活动
                'activity' => [
                    'point' => PointProductModel::getApplyCount(),
                    'bargain' => BargainProductModel::getApplyCount(),
                    'assemble' => AssembleProductModel::getApplyCount(),
                    'seckill' => SeckillProductModel::getApplyCount(),
                    'advance' => AdvanceProductModel::getApplyCount(),
                ],
                // 待审核
                'audit' => [
                    'comment' => $this->getReviewCommentTotal(),
                    'product' => $this->ProductModel->getProductTotal([
                        'audit_status' => 0
                    ]),
                ]
            ],
        ];
        //数据升降比例
        $data['top_data']['product_rate'] = $data['top_data']['product_yesterday'] > 0 ? round(($data['top_data']['product_today'] - $data['top_data']['product_yesterday']) / $data['top_data']['product_yesterday'] * 100, 2) : round($data['top_data']['product_today'] * 100, 2);
        $data['top_data']['user_rate'] = $data['top_data']['user_yesterday'] > 0 ? round(($data['top_data']['user_today'] - $data['top_data']['user_yesterday']) / $data['top_data']['user_yesterday'] * 100, 2) : round($data['top_data']['user_today'] * 100, 2);
        $data['top_data']['order_rate'] = $data['top_data']['order_yesterday'] > 0 ? round(($data['top_data']['order_today'] - $data['top_data']['order_yesterday']) / $data['top_data']['order_yesterday'] * 100, 2) : round($data['top_data']['order_today'] * 100, 2);
        $data['top_data']['supplier_rate'] = $data['top_data']['supplier_yesterday'] > 0 ? round(($data['top_data']['supplier_today'] - $data['top_data']['supplier_yesterday']) / $data['top_data']['supplier_yesterday'] * 100, 2) : round($data['top_data']['supplier_today'] * 100, 2);
        //商品销售排行
        $data['productRank'] = (new ProductRankingService())->getSaleTimeRanking($param);
        //销售额概况
        $data['saleData'] = $this->getSaleByDate($param['sale_time']);
        //用户数据
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
                'user_num' => $this->getUserTotal($day),//新增用户
                'visit_num' => (new VisitModel())->getVisitData($day, null, 'visit_user'),//访问用户
                'total_num' => $this->UserModel->getUserData(null, $day, 'user_total'),//累计用户
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
                'total_money' => $this->OrderModel->getOrderData($day, null, 'order_total_price'),
            ];
            $endTime = $day;
        }
        $startTime = $days[0];
        $detail['data'] = $data;
        $detail['days'] = $dateInfo['time'];
        $detail['saleMoney'] = $this->OrderModel->getOrderData($startTime, $endTime, 'order_total_price');
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
     * 最近七天日期
     */
    private function getLately7days()
    {
        // 获取当前周几
        $date = [];
        for ($i = 0; $i < 7; $i++) {
            $date[] = date('Y-m-d', strtotime('-' . $i . ' days'));
        }
        return array_reverse($date);
    }

    /**
     * 获取商品总量
     */
    private function getProductTotal($day = "")
    {
        return number_format($this->ProductModel->getProductTimeTotal($day));
    }

    /**
     * 获取待审核提现总数量
     */
    private function getAgentApplyTotal($apply_status)
    {
        $model = new AgentCashModel;
        return number_format($model->getAgentApplyTotal($apply_status));
    }

    /**
     * 获取用户总量
     */
    private function getUserTotal($day = null)
    {
        return number_format($this->UserModel->getUserTotal($day));
    }

    /**
     * 获取订单总量
     */
    private function getOrderTotal($day = null)
    {
        return number_format($this->OrderModel->getOrderData($day, null, 'order_total'));
    }

    /**
     * 获取待处理订单总量
     */
    private function getReviewOrderTotal()
    {
        return number_format($this->OrderModel->getReviewOrderTotal());
    }

    /**
     * 获取售后订单总量
     */
    private function getRefundOrderTotal()
    {
        return number_format($this->OrderRefund->getRefundOrderTotal());
    }

    /**
     * 获取平台售后订单总量
     */
    private function getPlateOrderTotal()
    {
        return number_format($this->OrderRefund->getPlateOrderTotal());
    }

    /**
     * 获取供应商总量
     */
    private function getSupplierTotal($day = "")
    {
        $model = new SupplierModel;
        return number_format($model->getSupplierTotalByDay($day));
    }

    /**
     * 获取待审核评价总量
     */
    private function getReviewCommentTotal()
    {
        $model = new Comment;
        return number_format($model->getReviewCommentTotal());
    }

    /**
     * 获取某天的总销售额
     */
    private function getOrderTotalPrice($day)
    {
        return sprintf('%.2f', $this->OrderModel->getOrderTotalPrice($day));
    }
}