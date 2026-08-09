<?php

namespace app\shop\model\plus\bargain;

use app\common\enum\order\OrderSourceEnum;
use app\common\model\plus\bargain\Task as TaskModel;
use app\common\model\plus\bargain\Active as ActiveModel;
use app\shop\model\order\Order as OrderModel;
use app\shop\model\plus\bargain\Product as BargainProductModel;

/**
 * 砍价模型
 */
class Active extends ActiveModel
{
    /**
     *列表
     */
    public function getList($param)
    {
        $model = $this;
        //检索活动名称
        if (isset($param['search']) && $param['search']) {
            $model = $model->where('title', 'like', '%' . trim($param['search']) . '%');
        }
        $list = $model->with(['file'])
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->where('is_delete', '=', 0)
            ->paginate($param);
        foreach ($list as $active) {
            //商品数
            $product_model = new BargainProductModel();
            $active['product_num'] = $product_model
                ->where('bargain_activity_id', '=', $active['bargain_activity_id'])
                ->where('status', '=', 10)
                ->where('is_delete', '=', 0)
                ->count();
            $active['product_audit_num'] = $product_model
                ->where('bargain_activity_id', '=', $active['bargain_activity_id'])
                ->where('status', '=', 0)
                ->where('is_delete', '=', 0)
                ->count();
            //订单数
            $active['total_sales'] = $product_model->where('bargain_activity_id', '=', $active['bargain_activity_id'])->sum('total_sales');
        }
        return $list;
    }

    /**
     * 添加
     * @param $data
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

    /**
     *删除
     */
    public function del()
    {
        //如果有正在拼团的商品
        $count = (new TaskModel())->whereRaw('(status = 0) OR (status = 1 and is_buy = 0)')
            ->where('bargain_activity_id', '=', $this['bargain_activity_id'])
            ->where('is_delete', '=', 0)
            ->count();
        if ($count > 0) {
            $this->error = '该活动下有正在砍价的订单';
            return false;
        }
        // 如果有未付款订单不能删除
        $count = (new OrderModel())->where('pay_status', '=', 10)
            ->where('order_source', '=', OrderSourceEnum::BARGAIN)
            ->where('activity_id', '=', $this['bargain_activity_id'])
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
     * 修改信息
     * @param $data
     */
    public function editBargain($param)
    {
        $data = array(
            'name' => $param['name'],
            'start_time' => $param['start_time']['value'],
            'end_time' => $param['end_time']['value'],
            'image_id' => $param['image_id'],
            'conditions' => $param['conditions'],
            'status' => $param['status']['value'],
            'sort' => $param['sort'],
        );
        $this->where('bargain_id', '=', $param['bargain_id'])->save($data);
        return true;
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
            'conditions' => $data['conditions'],
            'together_time' => $data['together_time'],
            'status' => $data['status'],
            'sort' => $data['sort'],
            'app_id' => self::$app_id,
        ];
        return $arr;
    }
}
