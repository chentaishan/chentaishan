<?php

namespace app\common\model\plus\bargain;

use app\common\model\BaseModel;

/**
 * 商品规格模型
 * @package app\common\model\plus\bargain
 */
class BargainSku extends BaseModel
{
    protected $name = 'bargain_product_sku';
    protected $pk = 'bargain_product_sku_id';

    /**
     *关联规格表
     */
    public function productSku()
    {
        return $this->belongsTo('app\\common\\model\\product\\ProductSku', 'product_sku_id', 'product_sku_id');
    }

    /**
     *关联商品表
     */
    public function product()
    {
        return $this->belongsTo('app\\common\\model\\plus\\bargain\\Product', 'bargain_product_id', 'bargain_product_id');
    }

    /**
     * 规格详情
     */
    public static function detail($seckill_product_sku_id, $with = [])
    {
        return (new static())->with($with)->find($seckill_product_sku_id);
    }
}