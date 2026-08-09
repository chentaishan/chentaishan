<?php

namespace app\job\event;

use app\job\model\order\Order as OrderModel;
use app\job\model\order\OrderAdvance as OrderAdvanceModel;
use think\facade\Cache;

/**
 * 预售订单事件管理
 */
class AdvanceOrder
{
    /**
     * 执行函数
     */
    public function handle()
    {
        try {
            $cacheKey = "task_space_advance_order_task";
            if (!Cache::has($cacheKey)) {
                // 定金订单行为管理
                $this->front();
                // 未支付尾款订单自动关闭
                $this->order();
                // 未支付尾款订单退定金
                $this->returnOrder();
                Cache::set($cacheKey, time(), 10);
            }
        } catch (\Throwable $e) {
            log_write('ORDER_ADVANCE TASK : ' . '__ ' . $e->getMessage(), 'task');
        }
        return true;
    }

    /**
     * 定金订单行为管理
     */
    private function front()
    {
        $OrderAdvanceModel = new OrderAdvanceModel();
        // 执行自动关闭
        $orderAdvanceIds = $OrderAdvanceModel->close();
        // 记录日志
        $this->dologs('frontClose', [
            'orderAdvanceIds' => json_encode($orderAdvanceIds),
        ]);
        return true;
    }

    /**
     * 未支付尾款订单自动关闭
     */
    private function order()
    {
        $OrderModel = new OrderModel();
        // 执行自动关闭
        $closeOrderIds = $OrderModel->close();
        // 记录日志
        $this->dologs('advanceClose', [
            'orderIds' => json_encode($closeOrderIds),
        ]);
        return true;
    }

    /**
     * 未支付尾款订单自动退定金
     */
    private function returnOrder()
    {
        $OrderAdvanceModel = new OrderAdvanceModel();
        // 执行自动关闭
        $closeOrderAdvanceIds = $OrderAdvanceModel->return();
        // 记录日志
        $this->dologs('advanceReturn', [
            'orderIds' => json_encode($closeOrderAdvanceIds),
        ]);
        return true;
    }

    /**
     * 记录日志
     */
    private function dologs($method, $params = [])
    {
        $value = 'behavior OrderAdvance --' . $method;
        foreach ($params as $key => $val)
            $value .= ' --' . $key . ' ' . $val;
        return log_write($value, 'task');
    }

}
