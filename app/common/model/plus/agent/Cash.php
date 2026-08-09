<?php

namespace app\common\model\plus\agent;

use app\common\model\BaseModel;

/**
 * 分销商提现明细模型
 */
class Cash extends BaseModel
{
    protected $name = 'agent_cash';
    protected $pk = 'id';

    /**
     * 打款方式
     * @var array
     */
    public $payType = [
        10 => '微信',
        20 => '支付宝',
        30 => '银行卡',
    ];

    /**
     * 申请状态
     * @var array
     */
    public $applyStatus = [
        10 => '待审核',
        20 => '审核通过',
        30 => '驳回',
        40 => '已打款',
        50 => '待用户确认收款',
    ];

    /**
     * 关联分销商用户表
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('User');
    }

    /**
     * 提现详情
     */
    public static function detail($id)
    {
        return (new static())->find($id);
    }

    /**
     * 审核状态
     * @param $value
     * @return array
     */
    public function getApplyStatusAttr($value)
    {
        $method = [10 => '待审核', 20 => '审核通过', 30 => '驳回', 40 => '已打款', 50 => '待用户确认收款'];
        return ['text' => $method[$value], 'value' => $value];
    }

    /**
     * 确认已打款
     */
    public function money()
    {
        $this->startTrans();
        try {
            // 更新申请状态
            $data = ['apply_status' => 40, 'audit_time' => time()];
            self::update($data, ['id' => $this['id']]);

            // 更新分销商累积提现佣金
            User::totalMoney($this['user_id'], $this['money']);

            // 记录分销商资金明细
            Capital::add([
                'user_id' => $this['user_id'],
                'flow_type' => 20,
                'money' => -$this['money'],
                'describe' => '申请提现',
            ]);
            // 发送模板消息
            //(new Message)->withdraw($this);
            // 事务提交
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

}