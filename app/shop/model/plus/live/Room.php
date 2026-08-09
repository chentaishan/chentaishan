<?php

namespace app\shop\model\plus\live;

use app\common\model\plus\live\Room as RoomModel;

/**
 * 房间模型
 */
class Room extends RoomModel
{
    /**
     * 列表
     */
    public function getList($params)
    {
        return $this->with(['share', 'user','supplier','cover'])
            ->order(['create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 删除记录 (软删除)
     */
    public function setDelete($where)
    {
        return self::update(['is_delete' => 1], $where);
    }

    public function edit($data)
    {
        return $this->save([
            'virtual_num' => $data['virtual_num'],
            'is_top' => $data['is_top'],
            'is_delete' => $data['is_delete'],
            'sort' => $data['sort'],
        ]);
    }
    /**
     * 审核
     */
    public function audit($data)
    {   
        if($this['live_status']!=0){
            $this->error = "已经审核";
            return false;
        }
        $data['status'] = $data['status']==10?102:100;
        return $this->save([
            'live_status' => $data['status'],
            'audit_remark' => $data['remark'],
        ]);
    }
}
