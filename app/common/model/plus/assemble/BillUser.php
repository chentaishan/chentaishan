<?php

namespace app\common\model\plus\assemble;

use app\common\model\BaseModel;

/**
 * 参与记录模型
 */
class BillUser extends BaseModel
{
    protected $name = 'assemble_bill_user';
    protected $pk = 'assemble_bill_user_id';


    public static function detail($seckill_product_sku_id, $with = [])
    {
        return (new static())->with($with)->find($seckill_product_sku_id);
    }

    /**
     * 关联创建者
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id')
            ->field(['user_id', 'nickName', 'avatarUrl']);
    }
    /**
     *关联商品sku表
     */
    public function productSku()
    {
        return $this->belongsTo('app\\common\\model\\product\\ProductSku', 'product_sku_id', 'product_sku_id');
    }
}