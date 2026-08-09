<?php

namespace app\common\model\order;

use think\Model;

/**
 * 账单明细表
 */
class CloudBillRecord extends Model
{
    protected $pk = 'bill_record_id';
    protected $name = 'cloud_bill_record';
    public function users(){
        return $this->hasOne('app\common\model\user\User','user_id','user_id');
    }

}