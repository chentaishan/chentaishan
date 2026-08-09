<?php

namespace app\common\model\video;

use app\common\model\BaseModel;

/**
 * 视频模型
 */
class Video extends BaseModel
{
    protected $pk = 'video_id';
    protected $name = 'video';

    // 隐藏字段
    protected $hidden = ['is_delete', 'app_id'];

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo('app\\common\\model\\video\\VideoCategory', 'category_id', 'category_id');
    }

    /**
     * 获取器：格式化duration为时:分:秒
     */
    public function getDurationTextAttr($value, $data)
    {
        $duration = isset($data['duration']) ? (int)$data['duration'] : 0;
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * 视频详情
     */
    public static function detail($video_id)
    {
        return self::with(['category'])
            ->where('video_id', '=', $video_id)
            ->where('is_delete', '=', 0)
            ->find();
    }

    /**
     * 获取视频列表
     */
    public function getList($data)
    {
        $model = $this->with(['category'])
            ->where('is_delete', '=', 0);

        // 按分类筛选
        if (isset($data['category_id']) && $data['category_id'] > 0) {
            $model = $model->where('category_id', '=', $data['category_id']);
        }
        // 搜索
        if (isset($data['search']) && !empty($data['search'])) {
            $model = $model->where('title', 'like', '%' . $data['search'] . '%');
        }

        return $model->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($data);
    }

    /**
     * 新增视频
     */
    public function add($data)
    {
        $data['app_id'] = self::$app_id;
        return $this->save($data);
    }

    /**
     * 编辑视频
     */
    public function edit($data)
    {
        return $this->save($data);
    }

    /**
     * 软删除
     */
    public function setDelete()
    {
        return $this->save(['is_delete' => 1]);
    }
}
