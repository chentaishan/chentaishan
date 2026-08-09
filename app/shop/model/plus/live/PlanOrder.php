<?php

namespace app\shop\model\plus\live;

use app\common\model\plus\live\PlanOrder as PlanOrderModel;

/**
 * 充值模型
 */
class PlanOrder extends PlanOrderModel
{
    /**
     * 列表
     */
    public function getList($params)
    {   
        $model = $this;
        if (isset($params['search']) && $params['search'] != '') {
            $model = $model->where('user.nickName', 'like', '%' . trim($params['search']) . '%');
        }
        //搜索时间段
        if (isset($params['value1']) && $params['value1'] != '') {
            $sta_time = array_shift($params['value1']);
            $end_time = array_pop($params['value1']);
            $model = $model->whereBetweenTime('p.create_time', $sta_time, $end_time);
        }
        //搜索支付状态
        if (isset($params['pay_status']) && $params['pay_status'] != '') {
            $model = $model->where('p.pay_status', '=', $params['pay_status']);
        }
        return $model->alias('p')
            ->join('user', 'user.user_id = p.user_id')
            ->with(['user'])
            ->field('p.*')
            ->order(['p.create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 添加新记录
     */
    public function add($data)
    {
        $data['app_id'] = self::$app_id;
        return $this->save($data);
    }

    /**
     * 更新记录
     */
    public function edit($data)
    {
        return $this->save($data);
    }

    /**
     * 删除记录 (软删除)
     */
    public function setDelete($where)
    {
        return self::update(['is_delete' => 1], $where);
    }
}
