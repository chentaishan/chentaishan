<?php

namespace app\supplier\controller\setting;

use app\supplier\controller\Controller;
use app\supplier\model\supplier\Service as ServiceModel;
use app\supplier\model\settings\Setting as SettingModel;
use app\supplier\model\supplier\Supplier as SupplierModel;
/**
 * 供应商客服
 */
class Service extends Controller
{
    /**
     * 修改信息
     */
    public function index(){
        $model = ServiceModel::detail($this->getSupplierId());
        if($this->request->isGet()){
            $model['service_open'] = SettingModel::getSysConfig()['service_open'];
            return $this->renderSuccess('', compact('model'));
        }
        if(ServiceModel::saveService($this->getSupplierId(), $this->postData())){
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError()?:'保存失败');
    }
}
