<?php

namespace app\supplier\controller\shop;

use app\supplier\controller\Controller;
use app\supplier\model\supplier\ServiceSecurity as ServiceSecurityModel;
use app\supplier\model\supplier\ServiceApply as ServiceApplyModel;

/**
 * 供应商服务保障
 */
class Security extends Controller
{
    /**
     * 基础信息
     */
    public function index()
    {
        $ServiceSecurityModel = new ServiceSecurityModel();
        $list = $ServiceSecurityModel->getList($this->getSupplierId());
        return $this->renderSuccess('', compact('list'));
    }

    //申请
    public function apply()
    {
        $data = $this->postData();
        $data['shop_supplier_id'] = $this->getSupplierId();
        $model = new ServiceApplyModel;
        if ($model->apply($data)) {
            return $this->renderSuccess('申请成功');
        }
        return $this->renderError($model->getError() ?: '提交失败');
    }

    //退出
    public function quit()
    {
        $data = $this->postData();
        $model = ServiceApplyModel::detail($data['service_security_id'], $this->getSupplierId());
        if ($model->quit()) {
            return $this->renderSuccess('退出成功');
        }
        return $this->renderError($model->getError() ?: '提交失败');
    }
}
