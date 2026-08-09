<?php

namespace app\shop\model\plus\live;

use app\common\model\plus\live\LiveProduct as LiveProductModel;

/**
 * 房间模型
 */
class LiveProduct extends LiveProductModel
{
    /**
     * 列表
     */
    public function getList($params)
    {
        $model = $this;
        if(isset($params['shop_supplier_id'])){
            $model = $model->where('shop_supplier_id', '=', $params['shop_supplier_id']);
        }
        return $model->with(['product.image.file'])->order(['create_time' => 'asc'])
            ->paginate($params);
    }

}
