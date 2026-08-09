<?php

namespace app\common\model\plus\buy;

use app\common\model\BaseModel;

/**
 * 买送商品模型
 */
class BuyActivityProduct extends BaseModel
{
    protected $pk = 'buy_product_id';
    protected $name = 'buy_activity_product';
    //附加字段
    protected $append = [];

    /**
     * 获取详情
     */
    public static function detail($buy_product_id)
    {
        return self::find($buy_product_id);
    }
}