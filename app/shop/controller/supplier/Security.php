<?php

namespace app\shop\controller\supplier;

use app\shop\controller\Controller;
use app\shop\model\supplier\ServiceSecurity as ServiceSecurityModel;

/**
 * 服务管理控制器
 */
class Security extends Controller
{
    /**
     * 获取列表
     */
    public function index()
    {
        // 列表
        $model = new ServiceSecurityModel;
        $category = $model->getAll();
        return $this->renderSuccess('', compact('category'));
    }

    /**
     * 添加
     */
    public function add()
    {
        $model = new ServiceSecurityModel;
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 编辑
     */
    public function edit($service_security_id)
    {
        // 分类详情
        $model = ServiceSecurityModel::detail($service_security_id);
        // 更新记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('更新成功');
        }
        return $this->renderError($model->getError() ?: '更新失败');
    }

    /**
     * 删除
     */
    public function delete($service_security_id)
    {
        $model = ServiceSecurityModel::detail($service_security_id);
        if (!$model->remove()) {
            return $this->renderError('该服务使用中，删除失败');
        }
        return $this->renderSuccess('删除成功');
    }
}