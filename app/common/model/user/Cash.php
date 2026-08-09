<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

class Cash extends BaseModel
{
    protected $name = 'user_cash';
    protected $pk = 'id';

    public $payType = [
        10 => '微信',
        20 => '支付宝',
        30 => '银行卡',
        50 => '待用户确认收款',
    ];

    public $applyStatus = [
        10 => '待审核',
        20 => '审核通过',
        30 => '驳回',
        40 => '已打款',
        50 => '待用户确认收款',
    ];

    public function user()
    {
        return $this->belongsTo('User');
    }

    public static function detail($id)
    {
        return (new static())->find($id);
    }

    public function getApplyStatusAttr($value)
    {
        $method = [
            10 => '待审核',
            20 => '审核通过',
            30 => '驳回',
            40 => '已打款',
            50 => '待用户确认收款',
        ];
        return ['text' => $method[$value] ?? '', 'value' => $value];
    }

    public function money()
    {
        $this->startTrans();
        try {
            self::update([
                'apply_status' => 40,
                'audit_time' => time(),
            ], ['id' => $this['id']]);

            User::totalMoney($this['user_id'], $this['money']);

            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }
}