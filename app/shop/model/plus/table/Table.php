<?php

namespace app\shop\model\plus\table;

use app\common\model\plus\table\Table as TableModel;

/**
 * 表单模型
 */
class Table extends TableModel
{
    /**
     * 获取列表
     */
    public function getList($data)
    {
        return $this->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($data);
    }

    /**
     * 获取优惠券列表
     */
    public function getAll()
    {
        return $this->field(['table_id', 'name'])->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->select();
    }

    /**
     * 添加新记录
     */
    public function add($data)
    {
        if (!isset($data['tableData']) || !$data['tableData']) {
            $this->error = "请填写完整字段信息";
            return false;
        }
        $data['content'] = json_encode($data['tableData'], JSON_UNESCAPED_UNICODE);
        $data['app_id'] = self::$app_id;
        return $this->save($data);
    }

    /**
     * 更新记录
     */
    public function edit($data)
    {
        if (!isset($data['tableData']) || !$data['tableData']) {
            $this->error = "请填写完整字段信息";
            return false;
        }
        $data['content'] = json_encode($data['tableData'], JSON_UNESCAPED_UNICODE);
        return $this->save($data);
    }

    /**
     * 删除记录 (软删除)
     */
    public function setDelete()
    {
        return $this->save([
            'is_delete' => 1
        ]);
    }

}
