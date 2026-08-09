<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

/**
 * 提现记录模型
 */
class WithdrawalRecord extends BaseModel
{
    protected $name = 'withdrawal_record';
    protected $pk = 'record_id';

    /**
     * 关联用户
     */
    public function user()
    {
        $module = self::getCalledModule() ?: 'common';
        return $this->belongsTo("app\\{$module}\\model\\user\\User", 'user_id', 'user_id');
    }

    /**
     * 状态文字
     */
    public function getStatusTextAttr($value, $data)
    {
        $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];
        return $statusMap[$data['status']] ?? '未知';
    }

    /**
     * 提现类型文字
     */
    public function getWithdrawTypeTextAttr($value, $data)
    {
        $typeMap = [1 => '余额提现', 2 => '积分提现'];
        return $typeMap[$data['withdraw_type']] ?? '未知';
    }
}
