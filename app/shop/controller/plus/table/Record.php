<?php

namespace app\shop\controller\plus\table;

use app\shop\controller\Controller;
use app\shop\model\plus\table\Record as RecordModel;
use app\shop\model\plus\table\Table as TableModel;

/**
 * 表单控制器
 */
class Record extends Controller
{

    /**
     * 优惠券列表
     */
    public function index()
    {
        $model = new RecordModel();
        $list = $model->getList($this->postData());
        // 所有表单
        $table_list = (new TableModel())->getAll();
        return $this->renderSuccess('', compact('list', 'table_list'));
    }

    /**
     * 删除优惠券
     */
    public function delete($table_record_id)
    {
        $model = RecordModel::detail($table_record_id);
        // 更新记录
        if ($model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    /**
     * 导出
     */
    public function export()
    {
        $model = new RecordModel();
        return $model->exportList($this->postData());
    }
}