<?php

namespace app\common\model\order;

use think\Model;

/**
 * 充值配置表
 */
class CloudPayConfig extends Model
{
    protected $pk = 'pay_config_id';
    protected $name = 'cloud_pay_config';


}