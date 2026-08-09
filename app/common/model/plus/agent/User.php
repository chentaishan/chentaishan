<?php

namespace app\common\model\plus\agent;

use app\common\model\BaseModel;
use app\common\model\plus\agent\GradeLog as GradeLogModel;
use app\common\enum\user\grade\ChangeTypeEnum;

/**
 * 分销商用户模型
 */
class User extends BaseModel
{
    protected $name = 'agent_user';
    protected $pk = 'user_id';

    /**
     * 关联会员记录表
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User');
    }

    /**
     * 关联推荐人表
     * @return \think\model\relation\BelongsTo
     */
    public function referee()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'referee_id', 'user_id');
    }

    /**
     * 关联分销等级记录表
     * @return \think\model\relation\BelongsTo
     */
    public function grade()
    {
        return $this->belongsTo('app\\common\\model\\plus\\agent\\Grade', 'grade_id', 'grade_id');
    }

    /**
     * 详情
     */
    public static function detail($user_id, $with = ['user', 'referee', 'grade'])
    {
        return (new static())->with($with)->find($user_id);
    }

    /**
     * 是否为分销商
     */
    public static function isAgentUser($user_id)
    {
        $agent = self::detail($user_id);
        return !!$agent && !$agent['is_delete'];
    }

    /**
     * 新增分销商用户记录
     * @param $user_id
     * @param $data
     * @return bool
     */
    public static function add($user_id, $data)
    {
        $model = static::detail($user_id) ?: new static;
        $model->save(array_merge([
            'user_id' => $user_id,
            'is_delete' => 0,
            'app_id' => $model::$app_id
        ], $data));
        event('AgentUserGrade', $model['referee_id']);
        return true;
    }

    /**
     * 发放分销商佣金
     * @param $user_id
     * @param $money
     * @return bool
     */
    public static function grantMoney($user_id, $money)
    {
        // 分销商详情
        $model = static::detail($user_id);
        if (!$model || $model['is_delete']) {
            return false;
        }
        // 累积分销商可提现佣金
        $model->where('user_id', '=', $user_id)->inc('money', $money)->update();
        // 记录分销商资金明细
        Capital::add([
            'user_id' => $user_id,
            'flow_type' => 10,
            'money' => $money,
            'describe' => '订单佣金结算',
            'app_id' => $model['app_id'],
        ]);
        return true;
    }

    /**
     * 详情
     */
    public static function agentCount($referee_id)
    {
        return (new static())->where('referee_id', '=', $referee_id)->count();
    }


    /**
     * 批量设置会员等级
     */
    public function upgradeGrade($user, $upgradeGrade)
    {
        // 更新会员等级的数据
        $this->where('user_id', '=', $user['user_id'])
            ->update([
                'grade_id' => $upgradeGrade['grade_id']
            ]);
        (new GradeLogModel)->save([
            'old_grade_id' => $user['grade_id'],
            'new_grade_id' => $upgradeGrade['grade_id'],
            'change_type' => ChangeTypeEnum::AUTO_UPGRADE,
            'user_id' => $user['user_id'],
            'app_id' => $user['app_id']
        ]);
        return true;
    }

    /**
     * 累计分销商成员数量
     */
    public static function setMemberInc($agent_id, $level)
    {
        $fields = [1 => 'first_num', 2 => 'second_num', 3 => 'third_num'];
        $model = static::detail($agent_id);
        return $model->where('user_id', '=', $agent_id)->inc($fields[$level])->update();
    }

    /**
     * 提现打款成功：累积提现佣金
     */
    public static function totalMoney($user_id, $money)
    {
        $model = self::detail($user_id);
        return $model->save([
            'freeze_money' => $model['freeze_money'] - $money,
            'total_money' => $model['total_money'] + $money,
        ]);
    }
}