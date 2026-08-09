<?php

namespace app\shop\controller\video;

use app\shop\controller\Controller;
use app\common\model\video\Video as VideoModel;
use app\common\model\video\VideoCategory as VideoCategoryModel;

/**
 * 视频管理控制器
 */
class Video extends Controller
{
    /**
     * 视频列表
     */
    public function index()
    {
        $model = new VideoModel;
        $list = $model->getList($this->postData());
        // 分类列表
        $category = VideoCategoryModel::getAll();
        return $this->renderSuccess('', compact('list', 'category'));
    }

    /**
     * 添加视频
     */
    public function add()
    {
        if ($this->request->isGet()) {
            // 返回分类列表供选择
            $category = VideoCategoryModel::getAll();
            return $this->renderSuccess('', compact('category'));
        }
        $model = new VideoModel;
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 编辑视频
     */
    public function edit($video_id)
    {
        $model = VideoModel::detail($video_id);
        if (!$model) {
            return $this->renderError('视频不存在');
        }
        if ($this->request->isGet()) {
            $category = VideoCategoryModel::getAll();
            return $this->renderSuccess('', compact('model', 'category'));
        }
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('更新成功');
        }
        return $this->renderError($model->getError() ?: '更新失败');
    }

    /**
     * 删除视频
     */
    public function delete($video_id)
    {
        $model = VideoModel::detail($video_id);
        if (!$model) {
            return $this->renderError('视频不存在');
        }
        if ($model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }
}
