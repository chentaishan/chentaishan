<?php

namespace app\common\model\file;

use app\common\model\BaseModel;

/**
 * 图库库模型
 */
class UploadImage extends BaseModel
{
    protected $pk = 'category_id';
    protected $name = 'image_bank';

    /**
     * 文件详情
     */
    public static function detail($category_id)
    {
        return (new static())->find($category_id);
    }

    /**
     * 添加新记录
     */
    public function add($data)
    {
        return $this->save($data);
    }

    /**
     * 添加新记录
     */
    public function edit($name)
    {
        return $this->save(['name' => $name]);
    }

    /**
     * 添加新记录
     */
    public function remove()
    {
        return $this->delete();
    }

    /**
     * 批量删除
     */
    public function deleteFiles($imageIds)
    {
        return $this->where('category_id', 'in', $imageIds)->delete();
    }

    /**
     * 获取列表记录
     */
    public function getList($data)
    {
        $model = $this->withoutGlobalScope();
        // 文件类别
        if (isset($data['parentId']) && $data['parentId']) {
            $model = $model->where('parent_id', '=', $data['parentId']);
        }
        // 查询列表数据
        return $model->where('image', '<>', '')
            ->order(['category_id' => 'desc'])
            ->paginate($data);
    }

    /**
     * 获取列表记录
     */
    public function getCategoryList()
    {
        $model = $this->withoutGlobalScope();
        // 查询列表数据
        return $model->where('parent_id', '=', 0)
            ->order(['sort' => 'asc', 'category_id' => 'desc'])
            ->select();
    }
}
