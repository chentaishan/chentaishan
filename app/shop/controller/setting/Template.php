<?php

namespace app\shop\controller\setting;

use app\shop\controller\Controller;
use app\shop\model\settings\DeliveryTemplate as DeliveryTemplateModel;

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

    /**
     * 添加
     */
    public function add()
    {
        $model = new DeliveryTemplateModel;
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 编辑
     */
    public function edit($template_id)
    {
        // 详情
        $model = DeliveryTemplateModel::detail($template_id);
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }
        // 更新记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('更新成功');
        }
        return $this->renderError($model->getError() ?: '更新失败');
    }

    /**
     * 删除
     */
    public function delete($template_id)
    {
        $model = DeliveryTemplateModel::detail($template_id);
        if (!$model->remove()) {
            return $this->renderError($model->getError() ?: '删除失败');
        }
        return $this->renderSuccess('删除成功');
    }

}