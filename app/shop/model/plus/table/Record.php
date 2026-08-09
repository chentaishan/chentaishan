<?php

namespace app\shop\model\plus\table;

use app\common\exception\BaseException;
use app\common\model\plus\table\Record as RecordModel;
use app\shop\service\order\ExportService;

/**
 * 表单记录模型
 */
class Record extends RecordModel
{
    /**
     * 获取表单记录列表
     */
    public function getList($data, $page = true)
    {
        $model = $this;
        if (isset($data['table_id']) && $data['table_id'] > 0) {
            $model = $model->where('record.table_id', '=', $data['table_id']);
        }
        if (isset($data['search']) && $data['search']) {
            $model = $model->where('user.user_id|user.nickName|user.mobile', 'like', '%' . $data['search'] . '%');
        }
        $model = $model->alias('record')
            ->field(['record.*'])
            ->with(['tableM', 'user'])
            ->join('table table', 'table.table_id = record.table_id')
            ->join('user user', 'user.user_id = record.user_id')
            ->where('record.is_delete', '=', 0)
            ->where('table.is_delete', '=', 0)
            ->where('user.is_delete', '=', 0)
            ->order(['record.create_time' => 'desc']);
        if ($page) {
            $list = $model->paginate($data);
            foreach ($list as &$item) {
                $item['tableData'] = json_decode($item['content']);
                unset($item['content']);
            }
        } else {
            $list = $model->select();
        }
        return $list;
    }

    /**
     * 删除记录 (软删除)
     */
    public function setDelete()
    {
        $this->startTrans();
        try {
            $this->save([
                'is_delete' => 1
            ]);
            (new Table())->where('table_id', '=', $this['table_id'])->dec('total_count')->update();
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 订单导出
     */
    public function exportList($data)
    {
        if (!isset($data['table_id']) || $data['table_id'] <= 0) {
            return "";
        }
        $tableInfo = (new Table())->find($data['table_id']);
        // 获取订单列表
        $list = $this->getList($data, false);
        // 导出excel文件
        return (new Exportservice)->tableList($tableInfo, $list);
    }

}
