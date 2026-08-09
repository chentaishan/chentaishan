<?php

namespace app\supplier\model\supplier;

use app\common\model\supplier\DepositRefund as DepositRefundModel;
use app\supplier\model\supplier\Supplier as SupplierModel;
use app\supplier\model\order\Order as OrderModel;
use app\supplier\model\product\Product as ProductModel;
use app\common\exception\BaseException;
/**
 * 供应商提现账号模型
 */
class DepositRefund extends DepositRefundModel
{
   
    /**
     * 提交申请
     */
    public function submit($supplier)
    {
        // 数据验证
        $this->validation($supplier);
         // 开启事务
        $this->startTrans();
        try {
            // 新增申请记录
            $this->save([
            'shop_supplier_id' => $supplier['shop_supplier_id'],
            'deposit_money'=>$supplier['deposit_money'],
            'app_id' => self::$app_id,
            ]);
            (new SupplierModel())->where(['shop_supplier_id' => $supplier['shop_supplier_id']])->update(['status'=>10]);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
       
     
    }

    /**
     * 数据验证
     */
    private function validation($supplier)
    {
        if ($supplier['deposit_money'] <= 0) {
            throw new BaseException(['msg' => '没有可退款金额']);
        }
        $isrefund = $this->where(['shop_supplier_id'=>$supplier['shop_supplier_id'],'status'=>0])->find();
        if($isrefund){
            throw new BaseException(['msg' => '申请退款中']);
        }
        // 筛选条件
        $filter = [];
        $filter['shop_supplier_id'] = $supplier['shop_supplier_id'];
        $filter['pay_status'] = 20;
        $filter['is_settled'] = 0;
        //查询是否有未完成订单
        $isOrder = (new OrderModel())->where($filter)->where('order_status', '<>', 20)->find();
        if($isOrder){
            throw new BaseException(['msg' => '存在订单未结算完成']);
        }
        //查询商品是否全部下架
        $filter = [];
        $filter['shop_supplier_id'] = $supplier['shop_supplier_id'];
        $filter['product_status'] = 10;
        $filter['audit_status'] = 10;
        $filter['is_delete'] = 0;
        $isProduct = (new ProductModel())->where($filter)->find();
        if($isProduct){
            throw new BaseException(['msg' => '存在商品未下架']);
        }
    }
}