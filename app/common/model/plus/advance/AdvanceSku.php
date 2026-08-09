<?php

namespace app\common\model\plus\advance;

use app\common\model\BaseModel;

/**
 *预售商品规格
 */
class AdvanceSku extends BaseModel
{
    protected $name = 'advance_product_sku';
    protected $pk = 'advance_product_sku_id';


    public static function detail($advance_product_sku_id, $with = [])
    {
        return (new static())->with($with)->where('advance_product_sku_id', '=', $advance_product_sku_id)->find();
    }

    /**
     *关联商品表
     */
    public function product()
    {
        return $this->belongsTo('app\\common\\model\\plus\\advance\\Product', 'advance_product_id', 'advance_product_id');
    }

    /**
     *关联商品sku表
     */
    public function productSku()
    {
        return $this->belongsTo('app\\common\\model\\product\\ProductSku', 'product_sku_id', 'product_sku_id');
    }
}