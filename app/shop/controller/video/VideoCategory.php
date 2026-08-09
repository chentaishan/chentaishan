<?php

namespace app\shop\controller\video;

use app\shop\controller\Controller;
use app\common\model\video\VideoCategory as VideoCategoryModel;

/**
 * 视频分类管理控制器
 */
class VideoCategory extends Controller
{
    /**
     * 分类列表
     */
    public function index()
    {
        $model = new VideoCategoryModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 添加分类
     */
    public function add()
    {
        if ($this->request->isGet()) {
            return $this->renderSuccess('');
        }
        $model = new VideoCategoryModel;
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 编辑分类
     */
    public function edit($category_id)
    {
        $model = VideoCategoryModel::detail($category_id);
        if (!$model) {
            return $this->renderError('分类不存在');
        }
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('更新成功');
        }
        return $this->renderError($model->getError() ?: '更新失败');
    }

    /**
     * 删除分类
     */
    public function delete($category_id)
    {
        $model = VideoCategoryModel::detail($category_id);
        if (!$model) {
            return $this->renderError('分类不存在');
        }
        if ($model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }
}
