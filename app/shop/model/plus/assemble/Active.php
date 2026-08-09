<?php

namespace app\shop\model\plus\assemble;

use app\common\enum\order\OrderSourceEnum;
use app\common\model\plus\assemble\Active as ActiveModel;
use app\shop\model\order\Order as OrderModel;
use app\shop\model\plus\assemble\Product as AssembleProductModel;
use app\common\model\plus\assemble\Bill as BillModel;

/**
 * 拼团活动模型
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
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($param);

        foreach ($list as $active) {
            //商品数,已审核通过
            $product_model = new AssembleProductModel();
            $active['product_num'] = $product_model
                ->where('assemble_activity_id', '=', $active['assemble_activity_id'])
                ->where('status', '=', 10)
                ->where('is_delete', '=', 0)
                ->count();
            $active['product_audit_num'] = $product_model
                ->where('assemble_activity_id', '=', $active['assemble_activity_id'])
                ->where('status', '=', 0)
                ->where('is_delete', '=', 0)
                ->count();
            //订单数
            $active['total_sales'] = $product_model->where('assemble_activity_id', '=', $active['assemble_activity_id'])->sum('total_sales');
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

    /**
     * 新增
     */
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

    /**
     * 修改
     */
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

    public function del()
    {
        //如果有正在拼团的商品
        $count = (new BillModel())->where('status', '=', 10)
            ->where('assemble_activity_id', '=', $this['assemble_activity_id'])
            ->count();
        if ($count > 0) {
            $this->error = '该活动下有正在拼团的订单';
            return false;
        }
        // 如果有未付款订单不能删除
        $count = (new OrderModel())->where('pay_status', '=', 10)
            ->where('order_source', '=', OrderSourceEnum::ASSEMBLE)
            ->where('activity_id', '=', $this['assemble_activity_id'])
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
     * @return array
     */
    private function setData($data)
    {
        $data['active_time'][0] = substr($data['active_time'][0], 0, 16);
        $data['active_time'][1] = substr($data['active_time'][1], 0, 16);
        $arr = [
            'image_id' => $data['image_id'],
            'title' => $data['title'],
            'start_time' => strtotime($data['active_time'][0] . ':00'),
            'end_time' => strtotime($data['active_time'][1] . ':59'),
            'join_end_time' => strtotime($data['join_end_time']),
            'app_id' => self::$app_id,
            'together_time' => $data['together_time'],
            'sort' => $data['sort'],
            'status' => $data['status'],
            'fail_type' => $data['fail_type'],
            'is_single' => $data['is_single'],
        ];
        return $arr;
    }

    /**
     * 获取拼团商品列表
     * @return \think\Collection
     */
    public function activityList()
    {
        $res = $this->where('start_time', '<=', time())
            ->where('end_time', '>=', time())->select();
        return !empty($res) ? array_column($res->toArray(), 'assemble_activity_id') : [];
    }
}