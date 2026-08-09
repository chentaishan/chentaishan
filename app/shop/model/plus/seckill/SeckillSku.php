<?php

namespace app\shop\model\plus\seckill;

use app\common\model\plus\seckill\SeckillSku as SeckillSkuModel;


/**
 * 秒杀商品sku模型
 */
class SeckillSku extends SeckillSkuModel
{

    public function delAll($seckill_product_id)
    {
        return $this->where('seckill_product_id', '=', $seckill_product_id)->delete();
    }
}