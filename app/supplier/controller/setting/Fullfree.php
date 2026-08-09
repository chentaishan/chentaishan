<?php

namespace app\supplier\controller\setting;

use app\supplier\controller\Controller;
use app\supplier\model\settings\Setting as SettingModel;

/**
 * 满额包邮控制器
 */
class Fullfree extends Controller
{
    /**
     *商品推荐
     */
    public function index()
    {
        $key = 'full_free';
        if($this->request->isGet()){
            $vars['values'] = SettingModel::getSupplierItem($key, $this->getSupplierId());
            return $this->renderSuccess('', compact('vars'));
        }
        $model = new SettingModel;
        if ($model->edit($key, $this->postData(),$this->getSupplierId())) {
            return $this->renderSuccess('操作成功');
        }
    }

}