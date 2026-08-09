<?php

namespace app\supplier\model\plus\advance;

use app\common\model\plus\advance\AdvanceSku as AdvanceSkuModel;


/**
 * Class Partake
 * 预售商品sku模型
 */
class AdvanceSku extends AdvanceSkuModel
{

    public function delAll($advance_product_id)
    {
        return $this->where('advance_product_id', '=', $advance_product_id)->delete();
    }
}