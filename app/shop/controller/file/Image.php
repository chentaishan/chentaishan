<?php

namespace app\shop\controller\file;

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
        $data = $this->postData();
        $data['app_id'] = 0;
        $list = (new UploadImageModel)->getlist($data);
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
}
