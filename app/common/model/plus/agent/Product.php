<?php

namespace app\common\model\plus\agent;

use app\common\model\BaseModel;

/**
 * 分销商用户模型
 */
class Product extends BaseModel
{
    protected $name = 'agent_product';
    protected $pk = 'product_id';

    /**
     * 超管用户信息
     */
    public static function detail($product_id)
    {
        return (new static())->where('product_id', '=', $product_id)->find();
    }

    /**
     * 关联供应商表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id')
            ->field(['shop_supplier_id', 'name', 'address', 'logo_id']);
    }

}