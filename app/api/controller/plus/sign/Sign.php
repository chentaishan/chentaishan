<?php

namespace app\api\controller\plus\sign;

use app\api\controller\Controller;
use app\api\model\settings\Setting as SettingModel;
use app\api\model\plus\sign\Sign as SignModel;

/**
 * 用户签到控制器
 */
class Sign extends Controller
{
    /**
     * 添加用户签到
     */
    public function add()
    {
        $model = new SignModel();
        $data = $model->add($this->getUser());
        if ($data) {
            return $this->renderSuccess('', compact('data'));
        }
        return $this->renderError($model->getError() ?: '签到失败，请重试');
    }

    /**
     * 签到页面
     */
    public function index()
    {
        $user = $this->getUser();   // 用户信息
        $model = new SignModel();
        //获取签到配置
        $sign_conf = SettingModel::getItem('sign');
        $data = (new $model)->getBaseData($user, $sign_conf);
        //连续签到奖励
        $days = $model->seriesDay($user, $sign_conf);
        //月签到本周期签到天数
        $signInfo = $model->where('user_id', '=', $user['user_id'])->order('create_time desc')->find();
        $lastDate = $signInfo && $days ? date('j', strtotime($signInfo['sign_date'])) : date('j') - 1;
        //周签到周第几天
        if ($signInfo && $days) {
            $week = date('w', strtotime($signInfo['sign_date']));
        } else {
            $week = date('w');
            $week = $week == 0 ? 7 : $week;
            $week = $week - 1;
        }
        $day_arr = [];
        if (isset($sign_conf['reward_data'])) {
            $day_arr = array_column($sign_conf['reward_data'], 'day');
        }
        $arr = [];
        $point = [];
        foreach ($day_arr as $key => $val) {
            if ($sign_conf['sign_type'] == 1 && ($day_arr[$key] > 7 || ($day_arr[$key] - $days) > (7 - $week))) {
                continue;
            } elseif ($sign_conf['sign_type'] == 2 && ($day_arr[$key] - $days) > (date('t') - $lastDate)) {
                continue;
            }
            if ($day_arr[$key] - $days > 0) {
                array_push($arr, $val - $days);
                $point[$val - $days]['points'] = isset($sign_conf['reward_data'][$key]['point']) && $sign_conf['reward_data'][$key]['is_point'] ? $sign_conf['reward_data'][$key]['point'] : 0;
                $coupon_num = 0;
                $couponList = isset($sign_conf['reward_data'][$key]['coupon']) && $sign_conf['reward_data'][$key]['is_coupon'] ? $sign_conf['reward_data'][$key]['coupon'] : '';
                if ($couponList) {
                    foreach ($couponList as $item) {
                        $coupon_num += $item['coupon_num'];
                    }
                }
                $point[$val - $days]['coupon'] = $couponList;
                $point[$val - $days]['coupon_num'] = $coupon_num;
            }
        }
        $data['arr'] = $arr;
        $data['point'] = $point;
        $data['today'] = date('j');
        return $this->renderSuccess('', compact('data'));
    }

    /**
     * 获取签到规则
     */
    public function getSign()
    {
        // 获取签到配置
        $sign_conf = SettingModel::getItem('sign');
        $detail = isset($sign_conf['content']) ? $sign_conf['content'] : '';
        return $this->renderSuccess('', compact('detail'));
    }

    /**
     * 签到记录
     */
    public function list()
    {
        $model = new SignModel;
        $list = $model->getList($this->getUser(), $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

}