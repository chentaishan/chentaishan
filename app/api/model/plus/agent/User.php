<?php

namespace app\api\model\plus\agent;

use app\common\model\plus\agent\User as UserModel;

/**
 * 分销商用户模型
 */
class User extends UserModel
{
    /**
     * 隐藏字段
     */
    protected $hidden = [
        'create_time',
        'update_time',
    ];

    /**
     * 资金冻结
     */
    public function freezeMoney($money)
    {
        return $this->save([
            'money' => $this['money'] - $money,
            'freeze_money' => $this['freeze_money'] + $money,
        ]);
    }



}