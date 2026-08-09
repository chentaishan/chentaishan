<?php

namespace app\api\controller\video;

use app\api\controller\Controller;
use app\common\model\video\Video as VideoModel;
use app\common\model\video\VideoCategory as VideoCategoryModel;

/**
 * 商学院视频控制器（用户端）
 */
class Video extends Controller
{
    /**
     * 获取视频分类列表（仅启用状态）
     */
    public function categoryList()
    {
        try {
            $list = VideoCategoryModel::getAll();
        } catch (\Throwable $e) {
            $list = [];
        }
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 获取视频列表
     */
    public function list()
    {
        $data = $this->postData();
        $model = new VideoModel;
        $list = $model->where('is_delete', '=', 0)
            ->where('status', '=', 1);

        // 按分类筛选
        if (isset($data['category_id']) && $data['category_id'] > 0) {
            $list = $list->where('category_id', '=', $data['category_id']);
        }

        $list = $list->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($data)
            ->each(function ($item) {
                $item['duration_text'] = $item->getDurationTextAttr(null, $item->toArray());
                return $item;
            });

        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 获取视频详情
     */
    public function detail($video_id)
    {
        $model = VideoModel::detail($video_id);
        if (!$model || $model['status'] != 1) {
            return $this->renderError('视频不存在');
        }
        // 增加浏览次数
        $model->where('video_id', '=', $video_id)->inc('view_count')->update();
        // 追加duration_text
        $model['duration_text'] = $model->getDurationTextAttr(null, $model->toArray());
        return $this->renderSuccess('', compact('model'));
    }
}
