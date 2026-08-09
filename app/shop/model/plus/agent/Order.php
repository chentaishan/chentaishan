<?php

namespace app\shop\model\plus\agent;

use app\common\model\plus\agent\Order as OrderModel;
use app\common\service\order\OrderService;
use app\shop\service\order\ExportService;

/**
 * 分销商订单模型
 */
class Order extends OrderModel
{
    /**
     * 获取分销商订单列表
     */
    public function getList($param)
    {
        $model = $this->alias('agent');
        // 检索查询条件
        if (isset($param['user_id']) && $param['user_id'] > 1) {
            $model = $model->where('first_user_id|second_user_id|third_user_id', '=', $param['user_id']);
        }
        if (isset($param['is_settled']) && $param['is_settled'] > -1) {
            $model = $model->where('agent.is_settled', '=', $param['is_settled']);
        }
        //搜索订单号
        if (isset($param['order_no']) && $param['order_no']) {
            $model = $model->where('order.order_no', 'like', '%' . trim($param['order_no']) . '%');
        }
        //搜索用户信息
        if (isset($param['search']) && $param['search']) {
            $model = $model->where('user.nickName|user.mobile', 'like', '%' . $param['search'] . '%');
        }
        // 获取分销商订单列表
        $data = $model->with([
            'agent_first',
            'agent_second',
            'agent_third'
        ])
            ->join('user', 'user.user_id=agent.user_id')
            ->join('order', 'order.order_id=agent.order_id')
            ->field('agent.*')
            ->order(['create_time' => 'desc'])
            ->paginate($param);
        if ($data->isEmpty()) {
            return $data;
        }
        // 获取订单的主信息
        $with = ['product' => ['image', 'refund'], 'address', 'user'];
        return OrderService::getOrderList($data, 'order_master', $with);
    }

    /**
     * 订单导出
     */
    public function exportList($user_id = null, $is_settled = -1)
    {
        $model = $this;
        // 检索查询条件
        if ($user_id > 1) {
            $model = $model->where('first_user_id|second_user_id|third_user_id', '=', $user_id);
        }
        if ($is_settled > -1) {
            $model = $model->where('is_settled', '=', $is_settled);
        }
        // 获取分销商订单列表
        $data = $model->with([
            'agent_first',
            'agent_second',
            'agent_third'
        ])
            ->order(['create_time' => 'desc'])
            ->select();
        // 获取订单的主信息
        $with = ['product' => ['image', 'refund'], 'address', 'user'];
        $list = OrderService::getOrderList($data, 'order_master', $with);
        // 导出excel文件
        (new Exportservice)->agentOrderList($list);
    }
}