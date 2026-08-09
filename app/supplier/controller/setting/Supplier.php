<?php

namespace app\supplier\controller\setting;

use app\common\enum\settings\DeliveryTypeEnum;
use app\supplier\controller\Controller;
use app\supplier\model\supplier\Supplier as SupplierModel;

/**
 * 供应商
 */
class Supplier extends Controller
{

    /**
     * 修改信息
     */
    public function index()
    {
        $model = (new SupplierModel())->where('shop_supplier_id','=',$this->getSupplierId())
            ->with(['logo'])
            ->field("shop_supplier_id,name,link_phone,address,description,logistics_type,logo_id,back_image,is_show,latitude,longitude")
            ->find();
        if ($this->request->isGet()) {
            // 配送方式
            $logistics_type = DeliveryTypeEnum::logistics();
            return $this->renderSuccess('', compact('model', 'logistics_type'));
        }
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('操作成功', compact('model'));
        }
        return $this->renderError($model->getError() ?: '保存失败');
    }
}
