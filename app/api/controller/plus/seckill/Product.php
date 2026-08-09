<?php

namespace app\api\controller\plus\seckill;

use app\api\controller\Controller;
use app\api\model\plus\seckill\Product as ProductModel;
use app\api\model\plus\seckill\Active as ActiveModel;
use app\api\model\settings\Setting as SettingModel;
use app\common\service\product\BaseProductService;

/**
 * 秒杀产品控制器
 */
class Product extends Controller
{
    /**
     * 秒杀活动
     */
    public function active()
    {
        $model = new ActiveModel();
        $list = $model->activityList();
        return $this->renderSuccess('', compact('list'));
    }
    /**
     * 秒杀商品
     */
    public function product($seckill_activity_id)
    {
        $detail = ActiveModel::detailWithTrans($seckill_activity_id);
        $list = (new ProductModel())->getActivityList($seckill_activity_id);
        return $this->renderSuccess('', compact('detail','list'));
    }
    /**
     * 秒杀商品详情
     */
    public function detail($seckill_product_id)
    {
        $model = new ProductModel();
        $detail = $model->getSeckillDetail($seckill_product_id);
        $active = ActiveModel::detailWithTrans($detail['seckill_activity_id']);
        $specData = BaseProductService::getSpecData($detail['product']);
        //是否显示店铺信息
        $store_open = SettingModel::getStoreOpen();
        return $this->renderSuccess('', compact('detail', 'active', 'specData', 'store_open'));
    }

}