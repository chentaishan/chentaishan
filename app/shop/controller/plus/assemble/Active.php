<?php

namespace app\shop\controller\plus\assemble;

use app\shop\controller\Controller;
use app\shop\model\plus\assemble\Active as ActiveModel;

/**
 * 拼团活动控制器
 */
class Active extends Controller
{
    /**
     * 活动会场列表
     */
    public function index()
    {
        $model = new ActiveModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 新增活动会场
     */
    public function add()
    {
        $model = new ActiveModel;
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }


    /**
     * 获取秒杀活动详情
     * @param $seckill_activity_id int 秒杀活动id
     * @return \think\response\Json
     */
    public function edit($assemble_activity_id)
    {
        if($this->request->isGet()){
            $detail = ActiveModel::detailWithTrans($assemble_activity_id);
            return $this->renderSuccess('', compact('detail'));
        }
        $model = ActiveModel::detail($assemble_activity_id);
        // 新增记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }
    /**
     * 删除活动
     */
    public function delete($assemble_activity_id)
    {
        // 活动会场详情
        $model = ActiveModel::detail($assemble_activity_id);
        if ($model->del()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }
}