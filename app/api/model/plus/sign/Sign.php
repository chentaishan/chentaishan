<?php

namespace app\api\model\plus\sign;

use app\common\model\plus\sign\Sign as SignModel;
use app\common\exception\BaseException;
use app\api\model\settings\Setting as SettingModel;

/**
 * 用户签到模型模型
 */
class Sign extends SignModel
{

    /**
     * @param $user_id int 用户id
     * @param $sign_date string 签到时间
     * @return int|mixed
     */
    public function getDays($user_id, $sign_date, $sign_data)
    {
        $row = $this->where('user_id', '=', $user_id)->order(['create_time' => 'desc'])->find();
        if (empty($row)) {
            return 1;
        }
        $dif = (strtotime($sign_date) - strtotime($row['create_time'])) / (24 * 60 * 60);
        if (strtotime($row['sign_date']) == strtotime($sign_date)) {
            throw new BaseException(['msg' => '今天已签到']);
        }
        if ($dif > 1) {
            return 1;
        }
        if ($sign_data['sign_type'] == 1) {
            $week = date('w');
            if ($week == 0) {
                $week = 7;
            }
            if ($row['days'] > $week) {
                $row['days'] = $week;
            }
        } elseif ($sign_data['sign_type'] == 2) {
            $day = date('j');
            if ($row['days'] > $day) {
                $row['days'] = $day;
            }
        }
        if ($dif < 1) {
            return $row['days'] + 1;
        }
    }

    /**
     * 签到
     */
    public function add($user)
    {
        // 更新记录
        $this->startTrans();
        try {
            //积分别名
            $points_name = SettingModel::getPointsName();
            //获取签到配置
            $sign_conf = SettingModel::getItem('sign');
            //签到日期
            $sign_date = date('Y-m-d', time());
            $user_id = $user['user_id'];

            //获取连续签到天数
            $days = $this->getDays($user_id, $sign_date, $sign_conf);

            //修改用户积分
            $result = $user->setSignAward($user_id, $days, $sign_conf, $sign_date);
            $prize = "";
            if ($result['points']) {
                $prize = $result['points'] . $points_name;
            }
            if ($result['coupon_num']) {
                $prize = $prize ? $prize . ',优惠券x' . $result['coupon_num'] : '优惠券x' . $result['coupon_num'];
            }
            $data = [
                'user_id' => $user_id,
                'sign_date' => date('Y-m-d', time()),
                'sign_day' => intval(date('d', time())),
                'days' => $days,
                'points' => $result['points'],
                'coupon' => $result['coupon'],
                'prize' => $prize,
                'app_id' => self::$app_id
            ];

            $this->save($data);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    public function getListByUserId($user_id)
    {
        $str = date('Y-m-d', time());
        $arr = explode('-', $str);
        $start_time = strtotime($arr[0] . '-' . $arr[1] . '-01');
        $list = $this->where('user_id', '=', $user_id)
            ->where('create_time', 'between', [$start_time, time()])
            ->order(['create_time' => 'desc'])->select()->toArray();

        $res = array_column($list, 'sign_day');
        $len = count($list);

        if ($len == 0) {
            $days = 0;
            $is_sign = 0;
        } else {
            $days = $len;
            $is_sign = ($list[$len - 1]['sign_date'] == date('Y-m-d', time())) ? 1 : 0;
        }

        return [$res, $days, intval(date('d', time())), $is_sign];
    }

    //查询累计签到天数
    private function getUserDay($user)
    {
        return $this->where('user_id', '=', $user['user_id'])->count();
    }

    //获取签到数据
    public function getBaseData($user, $signData)
    {
        $indexToday = 0;
        if ($signData['sign_type'] == 1) {
            $indexToday = date('w');
            if ($indexToday != 0) {
                $indexToday = $indexToday - 1;
            } else {
                $indexToday = 6;
            }
        }
        $data = [
            'user_point' => $user['points'],
            'days' => $this->getUserDay($user),
            'week' => $this->getWeek($user, $signData),
            'continuous_days' => $this->seriesDay($user, $signData),
            'period' => $this->getPeriod($signData),
            'list' => $this->signDay($user),
            'is_sign' => $this->isSign($user),
            'points' => $this->getTotalPoints($user),
            'coupon_num' => $this->getTotalCoupon($user),
            'max_day' => $this->getUserMaxDay($user),
            'sign_list' => $this->getList($user),
            'indexToday' => $indexToday,
        ];
        return $data;
    }

    //签到打卡时间
    private function getWeek($user, $signData)
    {
        $list = [];
        $coupon = "";
        if ($signData['is_coupon'] && $signData['coupon']) {
            $coupon = $signData['coupon'];
        }
        $day = date('Y-m-d');
        if ($signData['sign_type'] == 1) {//周循环
            $day = date('Y-m-d', (time() - ((date('w') == 0 ? 7 : date('w')) - 1) * 24 * 3600));
        }
        $month = date('m');
        //连续签到天数
        $days = $this->seriesDay($user, $signData);
        for ($i = 0; $i < 7; $i++) {
            $date = date('m-d', strtotime($day . '+' . $i . 'day'));
            $time = date('Y-m-d', strtotime($day . '+' . $i . 'day'));
            $isSign = 0;
            if ($signData['sign_type'] == 0) {
                if ($i == 0) {
                    $date = '今天';
                    $signInfo = $this->where('sign_date', '=', date('Y-m-d'))->where('user_id', '=', $user['user_id'])->find();
                    $days = $signInfo ? $days : $days + 1;
                    $increasePoints = $this->increasePoints($days, $signData);
                    $isSign = $signInfo ? 1 : 0;
                    $points = $signInfo ? $signInfo['points'] : $signData['ever_sign'] + $increasePoints;
                } elseif ($i == 1) {
                    $days = $days + 1;
                    $increasePoints = $this->increasePoints($days, $signData);
                    $date = '明天';
                    $points = $signData['ever_sign'] + $increasePoints;
                } else {
                    $days = $days + 1;
                    $increasePoints = $this->increasePoints($days, $signData);
                    $points = $signData['ever_sign'] + $increasePoints;
                }
            } elseif ($signData['sign_type'] == 1) {
                $signInfo = $this->where('sign_date', '=', $time)->where('user_id', '=', $user['user_id'])->find();
                if ($time == date('Y-m-d')) {
                    $days = $signInfo ? $days : $days + 1;
                    $increasePoints = $this->increasePoints($days, $signData);
                    $date = '今天';
                    $points = $signInfo ? $signInfo['points'] : $signData['ever_sign'] + $increasePoints;
                } elseif ($time == date('Y-m-d', strtotime(date('Y-m-d') . '+1 day'))) {
                    $days = $days + 1;
                    $increasePoints = $this->increasePoints($days, $signData);
                    $date = '明天';
                    $points = $signData['ever_sign'] + $increasePoints;
                } elseif (strtotime($time) < strtotime(date('Y-m-d'))) {
                    $points = $signInfo ? $signInfo['points'] : 0;
                } else {
                    $days = $signInfo ? $days : $days + 1;
                    $increasePoints = $this->increasePoints($days, $signData);
                    $points = $signData['ever_sign'] + $increasePoints;
                }
                $isSign = $signInfo ? 1 : 0;
            } elseif ($signData['sign_type'] == 2) {
                if ($i == 0) {
                    $date = '今天';
                    $signInfo = $this->where('sign_date', '=', date('Y-m-d'))->where('user_id', '=', $user['user_id'])->find();
                    $days = $signInfo ? $days : $days + 1;
                    $increasePoints = $this->increasePoints($days, $signData);
                    $isSign = $signInfo ? 1 : 0;
                    $points = $signInfo ? $signInfo['points'] : $signData['ever_sign'] + $increasePoints;
                } elseif ($i == 1) {
                    if (date('m', strtotime($time)) != $month) {
                        $month = date('m', strtotime($time));
                        $days = 0;
                    } else {
                        $days = $days + 1;
                    }
                    $increasePoints = $this->increasePoints($days, $signData);
                    $date = '明天';
                    $points = $signData['ever_sign'] + $increasePoints;
                } else {
                    if (date('m', strtotime($time)) != $month) {
                        $month = date('m', strtotime($time));
                        $days = 1;
                    } else {
                        $days = $days + 1;
                    }
                    $increasePoints = $this->increasePoints($days, $signData);
                    $points = $signData['ever_sign'] + $increasePoints;
                }
            }
            $list[] = [
                'date' => $date,
                'is_sign' => $isSign,
                'rank' => $points,
                'coupon' => $coupon
            ];
        }
        return $list;
    }

    //获取递增奖励
    private function increasePoints($days, $signData)
    {
        $increasePoints = 0;
        if ($signData['is_increase'] == 'true') {
            if ($days >= $signData['no_increase']) {
                $days = $signData['no_increase'] - 1;
            }
            $increasePoints = ($days - 1) * $signData['increase_reward'];
        }
        $increasePoints = $increasePoints > 0 ? $increasePoints : 0;
        return $increasePoints;
    }

    //连续签到天数
    public static function seriesDay($user, $signData)
    {
        $detail = (new static())->where('user_id', '=', $user['user_id'])
            ->order('create_time desc')
            ->find();
        $num = 0;
        $yesterday = date('Y-m-d', strtotime(date('Y-m-d') . '-1 day'));
        if ($detail) {
            if ($signData['sign_type'] == 0) {
                if ($detail['sign_date'] == date('Y-m-d') || $detail['sign_date'] == $yesterday) {
                    $num = $detail['days'];
                }
            } elseif ($signData['sign_type'] == 1) {//周循环
                if ($detail['sign_date'] == date('Y-m-d') || $detail['sign_date'] == $yesterday) {
                    $num = $detail['days'];
                }
                $week = date('w');
                if ($week == 0) {
                    $week = 7;
                }
                if ($num > $week) {
                    $num = $week;
                }
            } elseif ($signData['sign_type'] == 2) {//月循环
                if ($detail['sign_date'] == date('Y-m-d') || $detail['sign_date'] == $yesterday) {
                    $num = $detail['days'];
                }
                //本月开始时间
                $day = date('j');
                if ($num > $day) {
                    $num = $detail['sign_date'] == date('Y-m-d') ? $day : $day - 1;
                }
            }

        }
        return $num;
    }

    //获取周期
    private function getPeriod($signData)
    {
        $period = "";
        if ($signData['sign_type'] == 1) {
            $startDay = date('Y-m-d', (time() - ((date('w') == 0 ? 7 : date('w')) - 1) * 24 * 3600));
            $endDay = $time = date('Y-m-d', strtotime($startDay . '+6 day'));
            $period = "本周期为" . date('m.d', strtotime($startDay)) . "-" . date('m.d', strtotime($endDay));
        } elseif ($signData['sign_type'] == 2) {
            $period = "本月期为" . date('m.01') . "-" . date('m.t');
        }
        return $period;

    }

    //本月已签到天
    private function signDay($user)
    {
        return $this->where('user_id', '=', $user['user_id'])
            ->where('create_time', '>=', strtotime(date('Y-m')))
            ->column('sign_day');
    }

    //今日是否签到
    public static function isSign($user)
    {
        return (new static())->where('sign_date', '=', date('Y-m-d'))
            ->where('user_id', '=', $user['user_id'])
            ->count();
    }

    //累积获得积分
    public static function getTotalPoints($user)
    {
        return (new static())->where('user_id', '=', $user['user_id'])->sum('points');
    }

    //累积获得积分
    public static function getTotalCoupon($user)
    {
        $list = (new static())->where('user_id', '=', $user['user_id'])
            ->where('coupon', '<>', '')
            ->column('coupon');
        $num = 0;
        if (count($list) > 0) {
            foreach ($list as $item) {
                $item = json_decode($item, true);
                foreach ($item as $value) {
                    $num += $value['coupon_num'];
                }
            }
        }
        return $num;
    }

    //连续签到次数
    public static function getUserMaxDay($user)
    {
        return (new static())->where('user_id', '=', $user['user_id'])->max('days');
    }

    //签到记录
    public function getList($user, $data = null)
    {
        $model = $this->where('user_id', '=', $user['user_id'])
            ->order('create_time desc');
        if ($data) {
            $list = $model->paginate($data);
        } else {
            $list = $model->limit(5)->select();
        }
        return $list;
    }

}
