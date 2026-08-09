<?php

namespace app\api\model\user;

use app\api\model\settings\Setting as SettingModel;
use app\common\model\user\TaskLog as TaskLogModel;
use app\api\model\plus\sign\Sign as SignModel;
use think\facade\Cache;

/**
 * 任务记录模型
 */
class TaskLog extends TaskLogModel
{
    public function getTask($user)
    {
        //获取任务设置
        $data = SettingModel::getItem('task');
        foreach ($data['grow_task'] as &$item) {
            $item['status'] = $this->where('user_id', '=', $user['user_id'])
                ->where('task_type', '=', $item['task_type'])
                ->count();
        }
        foreach ($data['day_task'] as &$item) {
            $status = Cache::get('task_' . $item['task_type'] . date('Y-m-d') . $user['user_id']);
            $item['status'] = $status ? $status : 0;
        }
        //合并消费任务
        $order = [
            'name' => '消费任务',
            'image' => base_url() . 'image/task/order.png',
            'is_open' => '1',
            'task_type' => 'order',
            'rule' => '在商城消费获取积分',
            'points' => 0,
            'status' => 0
        ];
        $setting = SettingModel::getItem('points');
        // 条件：后台开启购物送积分
        if ($setting['is_shopping_gift']) {
            $data['day_task'] = array_merge($data['day_task'], [$order]);
        }
        //查询签到是否开启
        $sign = SettingModel::getItem('sign');
        if ($sign['is_open']) {
            $status = SignModel::where('user_id', '=', $user['user_id'])
                ->where('sign_date', '=', date('Y-m-d'))
                ->count();
            $array = [
                'name' => '签到打卡',
                'image' => base_url() . 'image/task/sign.png',
                'is_open' => '1',
                'task_type' => 'sign',
                'rule' => '每日签到获取积分',
                'points' => 0,
                'status' => $status
            ];
            $data['day_task'] = array_merge($data['day_task'], [$array]);
        }
        return $data;
    }
}