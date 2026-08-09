<?php

namespace app\api\model\plus\lottery;

use app\api\model\settings\Setting as SettingModel;
use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\model\plus\lottery\Lottery as LotteryModel;
use app\api\model\plus\coupon\UserCoupon;
use app\api\model\user\User as UserModel;
use app\common\model\plus\lottery\Record as RecordModel;
use app\common\model\user\BalanceLog as BalanceLogModel;

/**
 *转盘模型
 */
class Lottery extends LotteryModel
{
    /**
     * 转盘详情
     * @param $data
     */
    public function getDetail()
    {
        $detail = self::detail();
        if ($detail) {
            $LotteryPrize = new LotteryPrize;
            $detail['prize'] = $LotteryPrize::detail($detail['lottery_id']);
            $points_name = SettingModel::getPointsName();
            if ($detail['prize']) {
                foreach ($detail['prize'] as &$item) {
                    if ($item['type'] == 2) {
                        $item['name'] = $points_name ? $item['points'] . $points_name : $item['name'];
                    }
                }
            }
        }
        return $detail;
    }

    /**
     * 已抽奖次数
     * @param $data
     */
    public function getNum($user, $data)
    {
        //当天抽奖次数
        $time = strtotime(date('Y-m-d', time()));
        $num = (new RecordModel)->where('user_id', '=', $user['user_id'])->where('create_time', '>=', $time)->count();
        //可抽奖次数
        $nums = 0;
        //抽奖总次数
        $totalNum = (new RecordModel)->where('user_id', '=', $user['user_id'])->count();
        if ($data) {
            //剩余可抽奖次数
            $surplusNum = $data['total_num'] - $totalNum;
            $surplusNum = $surplusNum ? $surplusNum : 0;
            $nums = $data['times'] - $num;
            $nums = ($nums > $surplusNum) ? $surplusNum : $nums;
        }
        return $nums;
    }

    /**
     * 抽奖
     * @param $data
     */
    public function getdraw($user)
    {
        $this->startTrans();
        try {
            //奖品详情
            $detail = self::detail();
            if ($detail['status'] == 0) {
                $this->error = "抽奖未开启";
                return false;
            }
            if ($detail['start_time'] && strtotime($detail['start_time']) > time()) {
                $this->error = "抽奖活动未开始";
                return false;
            }
            if ($detail['end_time'] && strtotime($detail['end_time']) < time()) {
                $this->error = "抽奖活动已结束";
                return false;
            }
            // 是否是会员商品
            if ($detail['user_type'] == 1 && count($detail['grades']) > 0) {
                if (!in_array($user['grade_id'], $detail['grades'])) {
                    $this->error = '很抱歉，仅特定会员可抽奖';
                    return false;
                }
            }
            if ($user['points'] < $detail['points']) {
                $this->error = "积分不足";
                return false;
            }
            //判断今日抽奖次数
            $num = $this->getNum($user, $detail);
            if ($num <= 0) {
                $this->error = "抽奖次数已用完";
                return false;
            }
            $LotteryPrize = new LotteryPrize;
            $drawArr = $LotteryPrize::detail($detail['lottery_id']);
            $result = $this->getRand($drawArr); //根据概率获取奖项
            if (!$result) {
                $this->error = "礼品已抽完，请稍后再试";
                return false;
            }
            $LotteryPrize->where('prize_id', '=', $result['prize_id'])->inc('draw_num', 1)->update();
            $prize_num = 1;
            $product_price = 0;
            if ($result['type'] == 2) {
                $prize_num = $result['points'];
            } elseif ($result['type'] == 3) {
                $product_price = $result['product_price'];
            } elseif ($result['type'] == 4) {
                $prize_num = $result['balance'];
            }
            //添加中奖记录
            $record = [
                'record_name' => $result['name'],
                'user_id' => $user['user_id'],
                'prize_id' => $result['prize_id'],
                'prize_type' => $result['type'],
                'award_id' => $result['award_id'],
                'status' => $result['type'] == 3 ? 0 : 1,
                'app_id' => self::$app_id,
                'prize_num' => $prize_num,
                'is_play' => $result['is_play'],
                'image' => $result['image'],
                'product_price' => $product_price
            ];
            //更新用户积分
            $user->setIncPoints(-$detail['points'], '抽奖消费积分');
            //更新积分优惠券
            if (in_array($result['type'], [1, 2, 4])) {
                $this->addDraw($result, $user);
            }
            (new Record)->save($record);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    //更新积分优惠券
    private function addDraw($data, $user)
    {
        if ($data['type'] == 1) {//优惠券
            $UserCouponModel = new UserCoupon;
            $UserCouponModel->addUserCoupon([$data['award_id']], $user['user_id']);
        } elseif ($data['type'] == 2) {//积分
            // 重新查询用户
            $user = UserModel::detail($user['user_id']);
            $user->setIncPoints($data['points'], '抽奖获取积分');
        } elseif ($data['type'] == 4) {//余额
            (new UserModel)->where('user_id', '=', $user['user_id'])
                ->inc('balance', $data['balance'])
                ->update();
            BalanceLogModel::add(BalanceLogSceneEnum::LOTTERY, [
                'user_id' => $user['user_id'],
                'money' => $data['balance'],
                'app_id' => self::$app_id
            ], ['order_no' => '']);
        }
    }

    //计算中奖
    private function get_rand($proArr)
    {
        $result = '';
        //概率数组的总概率精度
        $proSum = array_sum($proArr);
        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);  //返回随机整数
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);
        return $result;
    }

    //计算中奖
    private function getRand($prize)
    {
        if (count($prize) <= 0) {
            return "";
        }
        $prize = $prize->toarray();
        $prizeArr = [];
        $default = [];
        $drawArr = [];
        foreach ($prize as $item) {
            if ($item['stock'] - $item['draw_num'] > 0) {
                for ($i = 0; $i < $item['weight']; $i++) {
                    $drawArr[] = $item['prize_id'];
                }
                $prizeArr[$item['prize_id']] = $item;
            }
            if ($item['is_default'] == 1) {
                $default = $item;//默认中奖项
            }
        }
        if (!$drawArr) {
            return $default;
        }
        shuffle($drawArr);
        $key = array_rand($drawArr);
        $result = isset($prizeArr[$drawArr[$key]]) ? $prizeArr[$drawArr[$key]] : '';
        return $result;
    }
}