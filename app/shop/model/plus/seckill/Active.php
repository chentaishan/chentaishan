<?php

namespace app\shop\model\plus\seckill;

use app\common\enum\order\OrderSourceEnum;
use app\common\model\plus\seckill\Active as ActiveModel;
use app\shop\model\plus\seckill\Product as SeckillProductModel;
use app\shop\model\order\Order as OrderModel;

/**
 * 秒杀活动
 */
class Active extends ActiveModel
{
    /**
     * @param $id
     * 参与记录列表
     * @return \think\Collection
     */
    public function getList($param)
    {
        $model = $this;
        if (isset($param['status']) && $param['status'] > -1) {
            switch ($param['status']) {
                case 0:
                    $model = $model->where('start_time', '>', time());
                    break;
                case 1;
                    $model = $model->where('start_time', '<', time())->where('end_time', '>', time());
                    break;
                case 2;
                    $model = $model->where('end_time', '<', time());
                    break;
            }
        }
        if (isset($param['title']) && !empty($param['title'])) {
            $model = $model->where('title', 'like', '%' . trim($param['title']) . '%');
        }
        $list = $model->with(['file'])
            ->where('is_delete', '=', 0)
            ->order('create_time', 'desc')
            ->paginate($param);
        foreach ($list as $active) {
            //商品数
            $product_model = new SeckillProductModel();
            $active['product_num'] = $product_model
                ->where('seckill_activity_id', '=', $active['seckill_activity_id'])
                ->where('status', '=', 10)
                ->where('is_delete', '=', 0)
                ->count();
            $active['product_audit_num'] = $product_model
                ->where('seckill_activity_id', '=', $active['seckill_activity_id'])
                ->where('status', '=', 0)
                ->where('is_delete', '=', 0)
                ->count();
            //订单数
            $active['total_sales'] = $product_model->where('seckill_activity_id', '=', $active['seckill_activity_id'])->sum('total_sales');
        }

        return $list;
    }

    /**
     *获取为开始的数据列表
     */
    public function getDatas()
    {
        return $this->where('end_time', '<', time())->select();
    }


    public function add($data)
    {
        $this->startTrans();
        try {
            $arr = $this->setData($data);
            $this->save($arr);
            // 事务提交
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    public function edit($data)
    {
        $this->startTrans();
        try {
            $arr = $this->setData($data);
            $this->save($arr);
            // 事务提交
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 活动删除
     */
    public function del()
    {
        // 如果有未付款订单不能删除
        $count = (new OrderModel())->where('pay_status', '=', 10)
            ->where('order_source', '=', OrderSourceEnum::SECKILL)
            ->where('activity_id', '=', $this['seckill_activity_id'])
            ->where('is_delete', '=', 0)
            ->count();
        if ($count > 0) {
            $this->error = '该活动下有未付款的订单';
            return false;
        }
        return $this->save([
            'is_delete' => 1
        ]);
    }

    /**
     * 验证并组装数据
     * @param $data array  添加/新增数据
     * @param $type  string 类型
     * @return array
     */
    private function setData($data)
    {
        $data['active_date'][0] = substr($data['active_date'][0], 0, 10);
        $data['active_date'][1] = substr($data['active_date'][1], 0, 10);
        $data['active_time'][0] = substr($data['active_time'][0], 0, 5);
        $data['active_time'][1] = substr($data['active_time'][1], 0, 5);
        $arr = [
            'image_id' => $data['image_id'],
            'title' => $data['title'],
            'status' => $data['status'],
            'sort' => $data['sort'],
            'start_time' => strtotime($data['active_date'][0] . ' ' . $data['active_time'][0] . ':00'),
            'end_time' => strtotime($data['active_date'][1] . ' ' . $data['active_time'][1] . ':59'),
            'join_end_time' => strtotime($data['join_end_time']),
            'day_start_time' => $data['active_time'][0] . ':00',
            'day_end_time' => $data['active_time'][1] . ':59',
            'app_id' => self::$app_id,
        ];

        return $arr;
    }

    /**
     * 获取diy秒杀活动商品
     */
    public function getDiyProduct()
    {
        $res = $this->with(['seckillProduct.seckillSku', 'seckillProduct.product'])->where('start_time', '<=', time())
            ->where('end_time', '>=', time())->find();
        if (isset($res['seckillProduct'])) {
            $list = [];
            foreach ($res['seckillProduct'] as $k => $val) {
                $list[$k]['product_name'] = $val['product']['product_name'];
                $list[$k]['product_id'] = $val['product_id'];
                $list[$k]['product_name'] = $val['product']['product_name'];
            }
            return $res['seckillProduct'];
        }
        return [];
    }
}