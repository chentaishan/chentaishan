<?php

namespace app\shop\model\plus\agent;

use app\shop\model\plus\agent\Referee as RefereeModel;
use app\common\model\plus\agent\User as UserModel;
use app\common\model\user\User as RealUserModel;

/**
 * 分销商用户模型
 * Class User
 * @package app\shop\model\plus\agent
 */
class User extends UserModel
{
    /**
     * 获取分销商用户列表
     */
    public function getList($data)
    {
        // 构建查询规则
        $model = $this->alias('agent')
            ->field('agent.*, user.nickName, user.avatarUrl')
            ->with(['referee', 'grade'])
            ->join('user', 'user.user_id = agent.user_id')
            ->where('agent.is_delete', '=', 0)
            ->order(['agent.create_time' => 'desc']);
        // 查询条件
        if (!empty($data['nick_name'])) {
            $model = $model->where('user.nickName|agent.real_name|agent.mobile', 'like', '%' . $data['nick_name'] . '%');
        }
        // 获取列表数据
        $list = $model->paginate($data);
        foreach ($list as $user) {
            $user['total_money'] = sprintf('%.2f', $user['money'] + $user['freeze_money'] + $user['total_money']);
        }
        return $list;
    }

    /**
     * 编辑分销商用户
     * @param $data
     * @return bool
     */
    public function edit($data)
    {
        // 开启事务
        $this->startTrans();
        try {
            if (isset($data['referee_id']) && $data['referee_id'] != $this['referee_id']) {
                $refereeInfo = RealUserModel::detail($data['referee_id']);
                if ($refereeInfo && $refereeInfo['referee_id'] == $this['user_id']) {
                    $this->error = '不允许互相绑定';
                    return false;
                }
                // 删除原推荐人关系
                $this->onDeleteReferee($this['user_id']);
                // 修改用户推荐人
                (new RealUserModel())->where('user_id', '=', $this['user_id'])->update([
                    'referee_id' => $data['referee_id']
                ]);
                // 记录推荐人关系，
                $data['referee_id'] > 0 && RefereeModel::updateRelation($this['user_id'], $data['referee_id']);
            }
            $this->save($data);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 删除分销商用户
     * @return mixed
     */
    public function setDelete()
    {
        return $this->transaction(function () {
            // 获取一级团队成员ID集
            $RefereeModel = new RefereeModel;
            $team1Ids = $RefereeModel->getTeamUserIds($this['user_id'], 1);
            if (!empty($team1Ids)) {
                // 一级团队成员归属到平台
                $this->setFromplatform($team1Ids);
                // 一级推荐人ID
                $referee1Id = RefereeModel::getRefereeUserId($this['user_id'], 1, true);
                if ($referee1Id > 0) {
                    // 一级推荐人的成员数量(二级)
                    $this->setDecTeamNum($referee1Id, 2, count($team1Ids));
                    // 一级推荐人的成员数量(三级)
                    $team2Ids = $RefereeModel->getTeamUserIds($this['user_id'], 2);
                    !empty($team2Ids) && $this->setDecTeamNum($referee1Id, 3, count($team2Ids));
                    // 二级推荐人的成员数量(三级)
                    $referee2Id = RefereeModel::getRefereeUserId($this['user_id'], 2, true);
                    $referee2Id > 0 && $this->setDecTeamNum($referee2Id, 3, count($team1Ids));
                    // 清空分销商下级成员与上级推荐人的关系记录
                    $RefereeModel->onClearTop(array_merge($team1Ids, $team2Ids));
                }
            }
            // 清空上级推荐记录
            $this->onDeleteReferee($this['user_id']);
            // 清空下级推荐记录
            $RefereeModel->onClearTeam($this['user_id']);
            // 标记当前分销商记录为已删除
            return $this->save([
                'is_delete' => 1
            ]);
        });
    }


    /**
     * 一级团队成员归属到平台
     * @param $userIds
     * @return false|int
     */
    private function setFromplatform($userIds)
    {
        return $this->where('user_id', 'in', $userIds)
            ->where('is_delete', '=', 0)
            ->save(['referee_id' => 0]);
    }

    /**
     * 递减分销商成员数量
     */
    private function setDecTeamNum($agent_id, $level, $number)
    {
        $field = [1 => 'first_num', 2 => 'second_num', 3 => 'third_num'];
        $agentUser = $this->where('user_id', '=', $agent_id)
            ->where('is_delete', '=', 0)
            ->field("first_num,second_num,third_num")
            ->find();
        if ($agentUser) {
            $total_num = $agentUser[$field[$level]] - $number;
            $total_num = $total_num > 0 ? $total_num : 0;
            $agentUser->save([$field[$level] => $total_num]);
        }
        return true;
    }

    /**
     * 提现驳回：解冻分销商资金
     */
    public static function backFreezeMoney($user_id, $money)
    {
        $model = self::detail($user_id);
        return $model->save([
            'money' => $model['money'] + $money,
            'freeze_money' => $model['freeze_money'] - $money,
        ]);
    }

    /**
     * 删除用户的上级推荐关系
     */
    public function onDeleteReferee($userId)
    {
        // 获取推荐人列表
        $list = RefereeModel::getRefereeList($userId);
        if (!$list->isEmpty()) {
            // 递减推荐人的下级成员数量
            foreach ($list as $item) {
                $item['agent1'] && $this->setDecTeamNum($item['agent_id'], $item['level'], 1);
            }
            // 清空上级推荐关系
            (new RefereeModel)->onClearReferee($userId);
        }
        return true;
    }

    /**
     * 获取平台的总销售额
     */
    public function getTotalMoney($type = 'all_money')
    {
        $model = $this;
        if ($type == 'money') {
            return $model->sum('money');
        } else if ($type == 'freeze_money') {
            return $model->sum('freeze_money');
        } else if ($type == 'total_money') {
            return $model->sum('total_money');
        } else if ($type == 'all_money') {
            return $model->sum('total_money') + $model->sum('freeze_money') + $model->sum('money');
        }
        return 0;
    }

    /**
     * 指定会员等级下是否存在用户
     */
    public static function checkExistByGradeId($gradeId)
    {
        $model = new static;
        return !!$model->where('grade_id', '=', (int)$gradeId)
            ->where('is_delete', '=', 0)
            ->value('user_id');
    }

    /**
     * 分销商详情
     */
    public static function getAgentDetail($user_id)
    {
        return (new static())->where('user_id', '=', $user_id)->where('is_delete', '=', 0)->find();
    }
}