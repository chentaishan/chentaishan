<?php

namespace app\common\model\plus\assemble;

use app\common\model\BaseModel;

/**
 * 参与记录模型
 */
class AssembleSku extends BaseModel
{
    protected $name = 'assemble_product_sku';
    protected $pk = 'assemble_product_sku_id';


    public static function detail($assemble_product_sku_id, $with = [])
    {
        return (new static())->with($with)->where('assemble_product_sku_id', '=', $assemble_product_sku_id)->find();
    }

    /**
     *关联商品表
     */
    public function product()
    {
        return $this->belongsTo('app\\common\\model\\plus\\assemble\\Product', 'assemble_product_id', 'assemble_product_id');
    }

    /**
     *关联商品sku表
     */
    public function productSku()
    {
        return $this->belongsTo('app\\common\\model\\product\\ProductSku', 'product_sku_id', 'product_sku_id');
    }
}