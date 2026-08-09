<?php

namespace app\shop\model\plus\buy;

use app\common\model\plus\buy\BuyActivity as BuyActivityModel;
use app\shop\model\supplier\Supplier as SupplierModel;

/**
 * 买送模型
 */
class BuyActivity extends BuyActivityModel
{
    /**
     * 编辑记录
     */
    public function edit($data)
    {
        return $this->allowField(['audit_status', 'status', 'update_time'])->save($data);
    }

    /**
     * 获取商户列表
     */
    public function getSupplierList()
    {
        $list = (new SupplierModel)->alias('s')
            ->join('buy_activity a', 's.shop_supplier_id=a.shop_supplier_id')
            ->where('a.is_delete', '=', 0)
            ->group('s.shop_supplier_id')
            ->field('s.shop_supplier_id,s.name')
            ->select();
        return $list;
    }

}