<?php

namespace app\common\model\plus\live;

use app\common\model\BaseModel;

/**
 * 房间商品模型
 */
class LiveProduct extends BaseModel
{
    protected $name = 'live_product';
    protected $pk = 'live_product_id';

    /**
     * 管理商品表
     */
    public function product()
    {
        return $this->belongsTo('app\\common\\model\\product\\Product', 'product_id', 'product_id')->hidden(['content']);
    }
}
