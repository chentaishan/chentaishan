<?php

namespace app\shop\model\plus\sign;

use app\common\model\plus\sign\Sign as SignModel;

/**
 * 用户签到模型模型
 */
class Sign extends SignModel
{
    /**
     * @param $data array 查询条件
     * @param $days array 连续签到天数数组
     * @param $sign_date array 最近签到时间
     * @return mixed
     */
    public function getList($data, $days)
    {
        $model = $this;

        if (isset($data['days']) && $data['days'] > -1) {
            $model = $model->where('sign.days', '=', $days[$data['days']]);
        }
        if (isset($data['create_time']) && $data['create_time']) {
            $model = $model->where('sign.create_time', 'between', [strtotime($data['create_time'][0]), strtotime($data['create_time'][1]) + 86399]);
        }
        if (isset($data['nickName']) && !empty($data['nickName'])) {
            $model = $model->where('user.nickName', 'like', '%' . trim($data['nickName']) . '%');
        }

        $list = $model->with(['user'])->alias('sign')
            ->join('user', 'user.user_id = sign.user_id')
            ->order(['sign.create_time' => 'desc'])
            ->paginate($data);
        foreach ($list as $item) {
            //首次签到时间
            $item['minDate'] = $this->where('user_id', '=', $item['user_id'])->order('create_time asc')->value('sign_date');
            //最后签到时间
            $item['endDate'] = $this->where('user_id', '=', $item['user_id'])->order('create_time desc')->value('sign_date');
            //上次签到时间
            $item['lastDate'] = $this->where('user_id', '=', $item['user_id'])
                ->where('sign_date', '<', $item['endDate'])
                ->order('create_time desc')
                ->value('sign_date');
            $item['totalDay'] = $this->where('user_id', '=', $item['user_id'])->count();
            $item['continuousDays'] = $this->where('user_id', '=', $item['user_id'])->max('days');
        }
        return $list;
    }

}
