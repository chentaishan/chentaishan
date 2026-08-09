<?php

namespace app\api\controller\plus\assemble;

use app\api\controller\Controller;
use app\api\model\plus\assemble\Product as ProductModel;
use app\api\model\plus\assemble\Active as ActiveModel;
use app\api\model\plus\chat\ChatRelation as ChatRelationModel;
use app\api\model\settings\Setting as SettingModel;
use app\common\model\supplier\Service as ServiceModel;
use app\common\service\product\BaseProductService;
use app\api\model\plus\assemble\Bill as BillModel;

/**
 * 拼团控制器
 */
class Product extends Controller
{
    /**
     * 拼团活动
     */
    public function active()
    {
        $model = new ActiveModel();
        $list = $model->activityList();
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 拼团商品
     */
    public function product($assemble_activity_id)
    {
        $detail = ActiveModel::detailWithTrans($assemble_activity_id);
        $list = (new ProductModel())->getActivityList($assemble_activity_id);
        return $this->renderSuccess('', compact('detail', 'list'));
    }

    /**
     * 拼团商品详情
     */
    public function detail($assemble_product_id)
    {
        $model = new ProductModel();
        //详情
        $detail = $model->getAssembleDetail($assemble_product_id);
        //活动
        $active = ActiveModel::detailWithTrans($detail['assemble_activity_id']);
        //规格
        $specData = BaseProductService::getSpecData($detail['product']);
        //拼团订单
        $model = new BillModel();
        $bill = $model->getBill($detail['assemble_product_id'], $detail['assemble_activity_id'], $detail['assemble_num']);
        //是否显示店铺信息
        $store_open = SettingModel::getStoreOpen();
        //店铺客服信息
        $mp_service = ServiceModel::detail($detail['shop_supplier_id']);
        //返回客服id
        $detail['chat_user_id'] = (new ChatRelationModel)->getChatUser($detail['shop_supplier_id'], $this->getUser(false));
        return $this->renderSuccess('', compact('detail', 'active', 'specData', 'bill', 'store_open', 'mp_service'));
    }
}