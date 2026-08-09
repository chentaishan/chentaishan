<?php

namespace app\supplier\controller\setting;

use app\supplier\controller\Controller;
use app\supplier\model\settings\DeliverySetting as DeliverySettingModel;
use app\supplier\model\settings\Express as ExpressModel;

/**
 * 面单配置控制器
 */
class Label extends Controller
{
    /**
     * 获取列表
     */
    public function index()
    {
        // 面单配置
        $model = new DeliverySettingModel;
        $list = $model->getList($this->postData(), $this->getSupplierId());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isGet()) {
            // 物流公司列表
            $model = new ExpressModel();
            $expressList = $model->getAll();
            return $this->renderSuccess('', compact('expressList'));
        }
        $model = new DeliverySettingModel;
        // 新增记录
        if ($model->add($this->postData(), $this->getSupplierId())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 编辑
     */
    public function edit($setting_id)
    {
        // 详情
        $model = DeliverySettingModel::detail($setting_id);
        if ($this->request->isGet()) {
            // 物流公司列表
            $ExpressModel = new ExpressModel();
            $expressList = $ExpressModel->getAll();
            return $this->renderSuccess('', compact('model', 'expressList'));
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
    public function delete($setting_id)
    {
        $model = DeliverySettingModel::detail($setting_id);
        if (!$model->remove()) {
            return $this->renderError($model->getError() ?: '删除失败');
        }
        return $this->renderSuccess('删除成功');
    }
}