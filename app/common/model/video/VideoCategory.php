<?php

namespace app\common\model\video;

use app\common\model\BaseModel;

/**
 * 视频分类模型
 */
class VideoCategory extends BaseModel
{
    protected $pk = 'category_id';
    protected $name = 'video_category';

    // 隐藏字段
    protected $hidden = ['is_delete', 'app_id'];

    /**
     * 关联视频
     */
    public function videos()
    {
        return $this->hasMany('app\\common\\model\\video\\Video', 'category_id', 'category_id');
    }

    /**
     * 分类详情
     */
    public static function detail($category_id)
    {
        return self::where('category_id', '=', $category_id)
            ->where('is_delete', '=', 0)
            ->find();
    }

    /**
     * 获取分类列表
     */
    public function getList($data)
    {
        return $this->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($data);
    }

    /**
     * 获取全部分类（启用状态）
     */
    public static function getAll()
    {
        return (new static)->where('is_delete', '=', 0)
            ->where('status', '=', 1)
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->select();
    }

    /**
     * 新增分类
     */
    public function add($data)
    {
        $data['app_id'] = self::$app_id;
        return $this->save($data);
    }

    /**
     * 编辑分类
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
