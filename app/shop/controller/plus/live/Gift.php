<?php

namespace app\shop\controller\plus\live;

use app\shop\controller\Controller;
use app\shop\model\plus\live\Gift as GiftModel;

/**
 * 礼物控制器
 */
class Gift extends Controller
{

    /**
     * 列表
     */
    public function index()
    {
        $model = new GiftModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 添加
     */
    public function add()
    {
        $model = new GiftModel();
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError('添加失败');
    }

    /**
     * 更新
     */
    public function edit($gift_id)
    {
        $detail = GiftModel::detail($gift_id);
        if($this->request->isGet()){
            return $this->renderSuccess('', compact('detail'));
        }
        if ($detail->edit($this->postData())) {
            return $this->renderSuccess('更新成功');
        }
        return $this->renderError('更新失败');
    }

    /**
     * 删除
     */
    public function delete($gift_id)
    {
        // 详情
        $model = new GiftModel;
        // 更新记录
        if ($model->setDelete(['gift_id' => $gift_id])) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError('删除失败');
    }

}