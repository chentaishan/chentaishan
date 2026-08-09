<?php

namespace app\shop\model\plus\agent;

use app\common\model\plus\agent\Grade as AgentGradeModel;
use app\shop\model\plus\agent\User as AgentUserModel;

/**
 * 用户会员等级模型
 */
class Grade extends AgentGradeModel
{
    /**
     * 获取列表记录
     */
    public function getList()
    {
        $list = $this->selectList();
        // 如果为空，则插入默认等级
        if(count($list) == 0){
            $this->save([
                'name' => '默认等级',
                'is_default' => 1,
                'weight' => 1,
                'grade_id' => Grade::getDefaultGradeId(),
                'app_id' => self::$app_id
            ]);
            // 更新之前的默认为0的id为此等级id
            (new AgentUserModel())->where('grade_id', '=', 0)->update([
                'grade_id' => $this['grade_id']
            ]);
            $list = $this->selectList();
        }
        return $list;
    }


    private function selectList(){
        return $this->where('is_delete', '=', 0)
            ->order(['weight' => 'asc', 'create_time' => 'asc'])
            ->select();
    }

    /**
     * 新增记录
     */
    public function add($data)
    {
        $data['app_id'] = self::$app_id;
        $data['is_default'] = 0;
        $data['remark'] = $this->setRemark($data);
        return $this->save($data);
    }

    /**
     * 编辑记录
     */
    public function edit($data)
    {
        if($this['is_default'] == 0){
            $data['remark'] = $this->setRemark($data);
        }
        return $this->save($data);
    }

    private function setRemark($data){
        $remark = '';
        if($data['open_agent_money'] == 1){
            $money = sprintf('%.2f',$data['agent_money']);
            $remark .= "推广金额满{$money}元";
        }
        if($data['open_agent_user'] == 1){
            if(!empty($remark)){
                $remark .= '\r\n';
            }
            $remark .= "直推分销商满{$data['agent_user']}";
        }
        return $remark;
    }

    /**
     * 软删除
     */
    public function setDelete()
    {
        // 判断该等级下是否存在会员
        if (AgentUserModel::checkExistByGradeId($this['grade_id'])) {
            return false;
        }
        return $this->save(['is_delete' => 1]);
    }

}