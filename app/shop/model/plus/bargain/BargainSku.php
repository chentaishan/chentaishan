<?php

namespace app\shop\model\plus\bargain;

use app\common\model\plus\bargain\BargainSku as BargainSkuModel;

/**
 * Class BargainProductSku
 * 砍价商品规格模型
 * @package app\shop\model\plus\bargain
 */
class BargainSku extends BargainSkuModel
{
    /**
     * @param $data
     * 保存数据
     * @return \think\Collection
     */
    public function editAll($data)
    {
        return self::saveAll($data);
    }

    /**
     *删除商品规格
     */
    public function delSku($id)
    {
        $this->where('bargain_product_id', '=', $id)->delete();
        return true;
    }
}