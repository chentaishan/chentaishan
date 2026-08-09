<?php

namespace app\api\model\plus\seckill;

use app\common\model\plus\seckill\Active as ActiveModel;
use app\common\service\product\BaseProductService;

/**
 * 秒杀活动模型
 */
class Active extends ActiveModel
{
    public function checkOrderTime($data)
    {
        $result = ['code' => 0];
        if ($data['start_time'] > time()) {
            $result = ['code' => 10, 10 => '该活动未开始'];
        }
        if ($data['end_time'] < time()) {
            $result = ['code' => 20, 20 => '该活动已结束'];
        }
        $now_start_time = strtotime(date('Y-m-d') . ' ' . $data['day_start_time']);
        $now_end_time = strtotime(date('Y-m-d') . ' ' . $data['day_end_time']);
        if ($now_start_time > time()) {
            $result = ['code' => 30, 30 => '该活动今天未开始'];
        }
        if ($now_end_time < time()) {
            $result = ['code' => 40, 40 => '该活动今天已结束'];
        }
        return $result;
    }

    /**
     * 取最近要结束的一条记录
     */
    public static function getActive()
    {
        return (new static())->where('start_time', '<', time())
            ->where('end_time', '>', time())
            ->where('status', '=', 1)
            ->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'asc'])
            ->find();
    }

    /**
     * 获取秒杀活动
     */
    public function activityList()
    {
        return $this->where('start_time', '<=', time())
            ->where('end_time', '>', time())
            ->where('status', '=', 1)
            ->where('is_delete', '=', 0)
            ->select();
    }

    /**
     * 获取秒杀商品
     * 6.10显示未开始
     */
    public static function getProductSecKillDetail($product_id)
    {
        $active = (new static())->alias('a')
            ->where('a.end_time', '>', time())
            ->where('a.status', '=', 1)
            ->where('a.is_delete', '=', 0)
            ->join('seckill_product s', 's.seckill_activity_id=a.seckill_activity_id')
            ->where('product_id', '=', $product_id)
            ->where('s.is_delete', '=', 0)
            ->field('s.*')
            ->find();
        $detail = "";
        if ($active) {
            $detail = (new Product())->getSeckillDetail($active['seckill_product_id']);
            $detail['active'] = self::detailWithTrans($active['seckill_activity_id']);
            $detail['specData'] = BaseProductService::getSpecData($detail['product']);
        }
        return $detail;
    }
}
