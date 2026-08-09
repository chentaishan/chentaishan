<?php

namespace app\common\service\product;

use app\common\exception\BaseException;
use app\common\model\plus\assemble\Product as AssembleProductModel;
use app\common\model\plus\bargain\Product as BargainProductModel;
use app\common\model\plus\point\Product as PointsProductModel;
use app\common\model\plus\seckill\Product as SeckillProductModel;
use app\shop\model\plus\advance\Product as AdvanceProductModel;

/**
 * 商品服务类,公共处理方法
 */
class BaseProductService
{
    /**
     * 商品多规格信息
     */
    public static function getSpecData($model = null)
    {
        // 商品sku数据
        if (!is_null($model) && $model['spec_type'] == 20) {
            return $model->getManySpecData($model['spec_rel'], $model['sku']);
        }
        return null;
    }

    /**
     * 验证商品是否允许删除
     */
    public static function checkSpecLocked($model = null, $scene = 'edit')
    {
        if ($model == null || $scene == 'copy') return false;
        $service = new static;
        // 积分
        if ($service->checkPointsProduct($model['product_id'])) return true;
        // 拼团
        if ($service->checkAssembleProduct($model['product_id'])) return true;
        // 砍价
        if ($service->checkBargainProduct($model['product_id'])) return true;
        // 秒杀
        if ($service->checkSeckillProduct($model['product_id'])) return true;
        return false;
    }

    /**
     * 验证商品是否允许删除
     */
    public static function checkProductLocked($model)
    {
        $service = new static;
        // 积分
        if ($service->checkPointsProduct($model['product_id'])) {
            throw new BaseException(['msg' => '当前商品正在参与积分商城活动，不允许删除']);
        }
        // 拼团
        if ($service->checkAssembleProduct($model['product_id'])) {
            throw new BaseException(['msg' => '当前商品正在参与拼团活动，不允许删除']);
        }
        // 砍价
        if ($service->checkBargainProduct($model['product_id'])) {
            throw new BaseException(['msg' => '当前商品正在参与砍价活动，不允许删除']);
        }
        // 秒杀
        if ($service->checkSeckillProduct($model['product_id'])) {
            throw new BaseException(['msg' => '当前商品正在参与秒杀活动，不允许删除']);
        }
        // 预售
        if ($service->checkAdvanceProduct($model['product_id'])) {
            throw new BaseException(['msg' => '当前商品正在参与预售活动，不允许删除']);
        }
        return false;
    }

    /**
     * 验证商品是否参与了积分商品
     */
    private function checkPointsProduct($productId)
    {
        return PointsProductModel::isExistProductId($productId);
    }

    /**
     * 验证商品是否参与了拼团商品
     */
    private function checkAssembleProduct($productId)
    {
        return AssembleProductModel::isExistProductId($productId);
    }

    /**
     * 验证商品是否参与了砍价商品
     */
    private function checkBargainProduct($productId)
    {
        return BargainProductModel::isExistProductId($productId);
    }

    /**
     * 验证商品是否参与了秒杀商品
     */
    private function checkSeckillProduct($productId)
    {
        return SeckillProductModel::isExistProductId($productId);
    }

    /**
     * 验证商品是否参与了预售商品
     */
    private function checkAdvanceProduct($productId)
    {
        return AdvanceProductModel::isExistProductId($productId);
    }
}