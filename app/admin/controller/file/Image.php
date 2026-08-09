<?php

namespace app\admin\controller\file;

use app\JjjController;
use app\common\model\file\UploadImage as UploadImageModel;

class Image extends JjjController
{
    /**
     * 文件库列表
     */
    public function list()
    {
        // 文件列表
        $list = (new UploadImageModel)->getlist($this->postData());
        return $this->renderSuccess('success', compact('list'));
    }

    /**
     * 图库分类列表
     */
    public function index()
    {
        // 分组列表
        $list = (new UploadImageModel)->getCategoryList();
        return $this->renderSuccess('success', compact('list'));
    }

    /**
     * 新增分组
     */
    public function addCategory()
    {
        $model = new UploadImageModel;
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 编辑分组
     */
    public function edit($category_id, $name)
    {
        $model = UploadImageModel::detail($category_id);
        if ($model->edit($name)) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     * 删除
     */
    public function delete($category_id)
    {
        $model = UploadImageModel::detail($category_id);
        if ($model->remove()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    /**
     * 批量删除文件
     */
    public function deleteFiles($imageIds)
    {
        $model = new UploadImageModel;
        if ($model->deleteFiles($imageIds)) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }
}
