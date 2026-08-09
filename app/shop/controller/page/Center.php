<?php

namespace app\shop\controller\page;

use app\shop\controller\Controller;
use app\shop\model\page\Page as PageModel;

/**
 * 个人中心控制器
 */
class Center extends Controller
{
    /**
     * 列表
     */
    public function index()
    {
        $model = new PageModel;
        //查找默认
        $default = $model::getDefault(30);
        $list = $model->getList($this->postData(), 30);
        return $this->renderSuccess('', compact('list', 'default'));
    }

    /**
     * 添加
     */
    public function add()
    {
        $model = new PageModel;
        if ($this->request->isGet()) {
            return $this->renderSuccess('', [
                'defaultData' => $model->getCenterDefaultItems(),
                'jsonData' => ['page' => $model->getDefaultPage(), 'items' => []],
            ]);
        }
        // 接收post数据
        $post = $this->postData();
        if (!$model->add(json_decode($post['params'], true), 30)) {
            return $this->renderError($model->getError() ?: '添加失败');
        }
        return $this->renderSuccess('添加成功');
    }

    /**
     * 修改
     */
    public function edit($page_id)
    {
        $model = PageModel::detail($page_id);
        if ($this->request->isGet()) {
            $jsonData = $model['page_data'];
            jsonRecursive($jsonData);
            return $this->renderSuccess('', [
                'defaultData' => $model->getCenterDefaultItems(),
                'jsonData' => $jsonData,
            ]);
        }
        // 接收post数据
        $post = $this->postData();
        if (!$model->edit(json_decode($post['params'], true))) {
            return $this->renderError($model->getError() ?: '更新失败');
        }
        return $this->renderSuccess('更新成功');
    }

    /**
     * 删除记录
     */
    public function delete($page_id)
    {
        // 详情
        $model = PageModel::detail($page_id);
        if (!$model->setDelete()) {
            return $this->renderError($model->getError() ?: '删除失败');
        }
        return $this->renderSuccess('删除成功');
    }

    /**
     * 设置默认
     */
    public function set($page_id)
    {
        // 页面详情
        $model = PageModel::detail($page_id);
        if (!$model->setDefault(30)) {
            return $this->renderError($model->getError() ?: '设置失败');
        }
        return $this->renderSuccess('设置成功');
    }
}
