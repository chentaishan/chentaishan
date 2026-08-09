<?php

namespace app\job\event;

use app\common\model\plus\agent\Grade as GradeModel;
use app\common\model\plus\agent\User as UserModel;

/**
 * 用户等级事件管理
 */
class AgentUserGrade
{
    /**
     * 执行函数
     */
    public function handle($userId)
    {
        // 设置用户的会员等级
        $this->setGrade($userId);
        return true;
    }

    /**
     * 设置等级
     */
    private function setGrade($userId)
    {
        log_write('分销商升级$user_id=' . $userId);
        // 用户模型
        $user = UserModel::detail($userId);
        if (!$user) {
            return false;
        }
        // 获取所有等级
        $list = GradeModel::getUsableList($user['app_id']);
        if ($list->isEmpty()) {
            return false;
        }
        // 遍历等级，根据升级条件 查询满足消费金额的用户列表，并且他的等级小于该等级
        $upgradeGrade = null;
        foreach ($list as $grade) {
            if ($grade['is_default'] == 1) {
                continue;
            }
            // 不自动升级
            if ($grade['auto_upgrade'] == 0) {
                continue;
            }
            $is_upgrade = $this->checkCanUpdate($user, $grade);
            if ($is_upgrade) {
                $upgradeGrade = $grade;
                continue;
            } else {
                break;
            }
        }
        if ($upgradeGrade) {
            $this->dologs('setAgentUserGrade', [
                'user_id' => $user['user_id'],
                'grade_id' => $upgradeGrade['grade_id'],
            ]);
            // 修改会员的等级
            (new UserModel())->upgradeGrade($user, $upgradeGrade);
        }
    }

    /**
     * 查询满足会员等级升级条件的用户列表
     */
    public function checkCanUpdate($user, $grade)
    {
        $agent_money = false;
        // 按推广金额升级
        if ($grade['open_agent_money'] == 1 && ($user['money'] + $user['freeze_money'] + $user['total_money']) >= $grade['agent_money']) {
            $agent_money = true;
        }
        $agent_user = false;
        // 按直推人数升级
        if ($grade['open_agent_user'] == 1 && UserModel::agentCount($user['user_id']) >= $grade['agent_user']) {
            $agent_user = true;
        }
        if ($grade['open_agent_money'] == 0 && $grade['open_agent_user'] == 0) {
            $agent_money = true;
            $agent_user = true;
        }
        if ($grade['condition_type'] == 'and') {
            return $agent_money && $agent_user;
        } else {
            return $agent_money || $agent_user;
        }
    }

    /**
     * 记录日志
     */
    private function dologs($method, $params = [])
    {
        $value = 'UserGrade --' . $method;
        foreach ($params as $key => $val)
            $value .= ' --' . $key . ' ' . $val;
        return log_write($value, 'task');
    }
}
