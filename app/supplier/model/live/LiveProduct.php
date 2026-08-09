<?php

namespace app\supplier\model\live;

use app\common\model\plus\live\LiveProduct as LiveProductModel;

/**
 * 直播商品模型
 */
class LiveProduct extends LiveProductModel
{
    /**
     * 列表
     */
    public function getList($params,$shop_supplier_id)
    {
        $model = $this;
     
        $model = $model->where('shop_supplier_id', '=', $shop_supplier_id);
   
        return $model->with(['product.image.file'])->order(['create_time' => 'asc'])
            ->paginate($params);
    }

}
