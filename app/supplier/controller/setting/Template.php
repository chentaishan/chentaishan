<?php

namespace app\supplier\controller\setting;

use app\supplier\controller\Controller;
use app\supplier\model\settings\DeliveryTemplate as DeliveryTemplateModel;

/**
 * 模板控制器
 */
class Template extends Controller
{
    /**
     * 获取列表
     */
    public function index()
    {
        // 列表
        $model = new DeliveryTemplateModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }
}