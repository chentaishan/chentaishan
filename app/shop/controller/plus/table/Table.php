<?php

namespace app\shop\controller\plus\table;

use app\shop\controller\Controller;
use app\shop\model\plus\table\Table as TableModel;

/**
 * 表单控制器
 */
class Table extends Controller
{

    /**
     * 优惠券列表
     */
    public function index()
    {
        $model = new TableModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 添加优惠券
     */
    public function add()
    {
        $model = new TableModel();
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError()?:'添加失败');
    }

    /**
     * 更新优惠券
     */
    public function edit($table_id)
    {
        $model = TableModel::detail($table_id);
        if($this->request->isGet()){
            return $this->renderSuccess('', compact('model'));
        }
        // 更新记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('更新成功');
        }
        return $this->renderError('更新失败');
    }

    /**
     * 删除优惠券
     */
    public function delete($table_id)
    {
        $model = TableModel::detail($table_id);
        // 更新记录
        if ($model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError()?:'删除失败');
    }
}