<?php

namespace app\shop\controller\plus\bargain;

use app\shop\controller\Controller;
use app\shop\model\plus\bargain\Active as ActiveModel;

/**
 * 砍价控制器
 */
class Active extends Controller
{
    /**
     * 砍价活动列表
     */
    public function index()
    {
        $model = new ActiveModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 新增砍价活动
     */
    public function add()
    {
        $model = new ActiveModel;
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?:'添加失败');
    }

    /**
     * 获取砍价活动详情
     */
    public function edit($bargain_activity_id)
    {
        if($this->request->isGet()){
            $detail = ActiveModel::detailWithTrans($bargain_activity_id);
            return $this->renderSuccess('', compact('detail'));
        }
        $model = ActiveModel::detail($bargain_activity_id);
        // 新增记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     *删除砍价活动
     */
    public function delete($bargain_activity_id)
    {
        // 活动会场详情
        $model = ActiveModel::detail($bargain_activity_id);
        if ($model->del()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }
}