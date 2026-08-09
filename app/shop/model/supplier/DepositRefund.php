<?php

namespace app\shop\model\supplier;

use app\common\model\supplier\DepositRefund as DepositRefundModel;
use app\supplier\model\supplier\Supplier as SupplierModel;
use app\shop\model\supplier\Capital as CapitalModel;

/**
 * 退押金申请模型类
 */
class DepositRefund extends DepositRefundModel
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
        return $model->join('supplier s', 's.shop_supplier_id=r.shop_supplier_id')
            ->field('r.*,s.name')
            ->order(['r.create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 退押金审核
     */
    public function submit($param)
    {
        $this->startTrans();
        try {
            $data = ['status' => $param['state']];
            $data['audit_time'] = time();
            // 更新申请记录
            $this->save($data);
            $supplier = SupplierModel::detail($this['shop_supplier_id']);

            if ($param['state'] == 2) {//拒绝退押金
                (new SupplierModel())->where(['shop_supplier_id' => $this['shop_supplier_id']])->update(['status' => 0]);
            } else if ($param['state'] == 1) {//同意退押金
                (new SupplierModel())->where(['shop_supplier_id' => $this['shop_supplier_id']])->update(['status' => 20, 'deposit_money' => 0, 'money' => $supplier['money'] + $this['deposit_money'], 'total_money' => $supplier['total_money'] + $this['deposit_money']]);
                $add = [
                    'shop_supplier_id' => $this['shop_supplier_id'],
                    'flow_type' => 10,
                    'money' => $this['deposit_money'],
                    'describe' => '退押金收入',
                ];
                //添加资金明细        
                CapitalModel::add($add);
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 获取退款数
     */
    public static function getRefundCount()
    {
        return (new static())->where('status', '=', 0)->count();
    }

    /**
     * 获取供应商申请统计数量
     */
    public function getRefundData($startDate, $endDate, $type)
    {
        $model = $this;
        $field = '';
        if ($type == 'supplier_refund') {
            $field = 'audit_time';
            $model = $model->where('status', '=', 1);
        } else if ($type == 'supplier_refund_apply') {
            $field = 'create_time';
        }
        if (!is_null($startDate)) {
            $model = $model->where($field, '>=', strtotime($startDate));
        }
        if (is_null($endDate)) {
            $model = $model->where($field, '<', strtotime($startDate) + 86400);
        } else {
            $model = $model->where($field, '<', strtotime($endDate) + 86400);
        }

        return $model->count();
    }
}
