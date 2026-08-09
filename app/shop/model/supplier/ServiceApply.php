<?php

namespace app\shop\model\supplier;

use app\common\model\supplier\ServiceApply as ServiceApplyModel;

/**
 * 供应商服务保障申请模型
 */
class ServiceApply extends ServiceApplyModel
{
    /**
     * 获取列表数据
     */
    public function getList($params)
    {
        $model = $this->alias('r');
        if ($params['status'] != '') {
            $model = $model->where('r.status', '=', $params['status']);
        }
        if (isset($params['search']) && $params['search']) {
            $model = $model->where('s.name', 'like', '%' . $params['search'] . '%');
        }
        // 查询列表数据
        return $model->with(['server'])
            ->join('supplier s', 's.shop_supplier_id=r.shop_supplier_id')
            ->field('r.*,name')
            ->order(['r.create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 退押金审核
     */
    public function verify($param)
    {
        $this->startTrans();
        try {
            $data = ['status' => $param['state']];
            // 更新申请记录
            $this->save($data);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 获取待审核数量
     */
    public static function getApplyCount()
    {
        return (new static())->where('status', '=', 0)
            ->count();
    }

    /**
     * 详情
     */
    public static function getdetail($service_apply_id)
    {
        return (new static())->where('service_apply_id', '=', $service_apply_id)->find();
    }
}