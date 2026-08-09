<?php

namespace app\shop\model\plus\assemble;

use app\common\model\plus\assemble\AssembleSku as AssembleSkuModel;


/**
 * 秒杀商品sku模型
 */
class AssembleSku extends AssembleSkuModel
{

    public function addAll($data)
    {
        return self::saveAll($data);
    }

    public function delAll($assemble_product_id)
    {
        return $this->where('assemble_product_id', '=', $assemble_product_id)->delete();
    }

    public function editAll($data)
    {
        return self::saveAll($data);
    }
}