<?php


namespace app\common\model\supplier;

use app\common\model\BaseModel;

/**
 * 退押金申请模型
 */
class DepositRefund extends BaseModel
{
    protected $pk = 'deposit_refund_id';
    protected $name = 'supplier_deposit_refund';

    /**
     * 关联供应商表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id');
    }

    /**
     * 详情
     */
    public static function detail($id)
    {
        return (new static())->find($id);
    }

    /**
     * 审核时间
     */
    public function getAuditTimeAttr($value, $data)
    {
        $text = $data['audit_time'] ? date('Y-m-d H:i:s', $value) : '';
        return ['text' => $text, 'value' => $value];
    }
}