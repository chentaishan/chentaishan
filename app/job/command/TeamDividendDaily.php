<?php
declare(strict_types=1);

namespace app\job\command;

use app\common\model\order\CloudBillRecord;
use app\common\service\activity\PartnerDividendDailyService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 团队每日分红（建议 crontab 每日 0:05 执行）
 * php think partner_dividend_daily
 * php think partner_dividend_daily --app_id=10001
 */
class TeamDividendDaily extends Command
{
    protected function configure()
    {
        $this->setName('team_dividend_daily')
            ->setDescription('团队分红')
            ->addOption('app_id', null, Option::VALUE_OPTIONAL, '仅执行指定 app_id', 0);
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('[' . date('Y-m-d H:i:s') . '] 团队每日分红开始');
        //团队分红
        $this->team_dividend();
        $output->writeln('[' . date('Y-m-d H:i:s') . '] 团队每日分红结束');
    }

    //团队分红定时任务
    public function team_dividend()
    {
        //== 每天统计，今天统计昨天==========================================
        $type = 2;
        $execNum = \app\common\model\order\CloudBonusRecord::where(['type'=>$type])->where('create_time','>=',date('Y-m-d 00:00:00'))->count();
        //$execNum = 0; //测试用
        if($execNum == 0){
            $yesterday = strtotime("-1 day");
            //测试用 上一期
            //$yesterday = strtotime("-2 day");
            //$yesterday = time(); //测试用
            $startTime = strtotime(date('Y-m-d 00:00:00',$yesterday)); // 昨天开始时间
            $endTime = strtotime(date('Y-m-d 23:59:59',$yesterday)); // 昨天结束时间
            var_dump("团队分红订单确认收货时间: ".date('Y-m-d H:i:s',$startTime).'-'.date('Y-m-d H:i:s',$endTime));
            // 获取合伙人分红分红比例
            $ratioF = \app\common\model\order\CloudRatio::whereIn('ratio_id',[85,86,87,90,91,92])->column('num','ratio_id');
            //福级升级
            $userList = \app\common\model\user\User::where(['is_delete'=>0])->field('user_id,f_level,referee_id')->select();
            if($userList){
                //整理用户信息
                $userRefereeList = [-1=>['user_id'=>-1,'referee_id'=>-2]];
                foreach ($userList as $item){
                    $userRefereeList[$item['user_id']] = $item;
                }
                //获取用户订单总值
                $userSumPay = [-1=>0];
                $userOrderPayList = \app\common\model\order\Order::query()
                    //->where('receipt_time', '>=', $startTime)
                    //->where('receipt_time', '<=', $endTime)
                    ->where(['order_status'=>30,'pay_status'=>20,'receipt_status'=>20])
                    ->whereIn('zone_type',[1])
                    ->group('user_id')
                    ->field('user_id,SUM(pay_price) AS price')
                    ->select();

                foreach ($userOrderPayList as $item){
                    if($item['price'] > 0){
                        $userSumPay[$item['user_id']] = $item['price'];
                    }
                }

                //升级需要小区业绩
                $f1 = ($ratioF && isset($ratioF[85]) && $ratioF[85] > 0 ? $ratioF[85] : 0);
                $f2 = ($ratioF && isset($ratioF[86]) && $ratioF[86] > 0 ? $ratioF[86] : 0);
                $f3 = ($ratioF && isset($ratioF[87]) && $ratioF[87] > 0 ? $ratioF[87] : 0);
                foreach ($userList as $user){
                    //3级不处理
                    if($user['f_level'] >= 3){
                        continue;
                    }
                    //获取团队业绩
                    $totalPrice = $this->getPlacementTotalPrice($user['user_id'], [1], $userRefereeList,$userSumPay);
                    //设置可升等级
                    $f_level = 0;
                    if($totalPrice >= $f1){
                        $f_level = 1;
                    }
                    if($totalPrice >= $f2){
                        $f_level = 2;
                    }
                    if($totalPrice >= $f3){
                        $f_level = 3;
                    }
                    //var_dump($user['user_id'].'==='.$totalPrice.'---'.$user['f_level'].'--'.$f_level);
                    if($f_level > $user['f_level']){
                        var_dump($user['user_id'].' level '.$user['f_level'].' to '.$f_level);
                        \app\common\model\user\User::where(['user_id'=>$user['user_id']])->update(['f_level'=>$f_level]);
                    }
                }
            }
            //exit;
            //获取每个等级分红人数
            //用户信息
            $userList = \app\common\model\user\User::where(['is_delete'=>0])->field('user_id,app_id,voucher,f_level')->select();
            if($userList->count() > 0) {
                //获取昨天确定收货销售总额
                $orderTotalPayPrice = \app\common\model\order\Order::query()
                    ->where('receipt_time', '>=', $startTime)
                    ->where('receipt_time', '<=', $endTime)
                    ->where(['order_status'=>30,'pay_status'=>20,'receipt_status'=>20])
                    ->whereIn('zone_type',[1])
                    ->sum('pay_price');
                //echo \app\common\model\order\Order::query()->getLastSql();
                /*echo $orderTotalPayPrice;
                exit;*/
                //设置分红比例参数信息
                $salespersonSet = [
                    // 1 福级1
                    1 => ['name' => '福级1', 'dividend_ratio' => ($ratioF && isset($ratioF[90]) && $ratioF[90] > 0 ? $ratioF[90] / 100 : 0),],
                    //2 福级2
                    2 => ['name' => '福级2', 'dividend_ratio' => ($ratioF && isset($ratioF[91]) && $ratioF[91] > 0 ? $ratioF[91] / 100 : 0),],
                    // 3 福级3
                    3 => ['name' => '福级3', 'dividend_ratio' => ($ratioF && isset($ratioF[92]) && $ratioF[92] > 0 ? $ratioF[92] / 100 : 0),],
                ];
                //设置每个等级人数和分红数,记录日志
                $salespersonLogs = [];
                for ($i = 1; $i <= 3; $i++) {
                    //获取每个等级分红人数
                    $salespersonNum = \app\common\model\user\User::query()
                        ->where(function ($query) use ($i) {
                            $query->whereOr(function ($q) use ($i) {
                                $q->where([
                                    ['f_level', '>', 0],
                                    ['f_level', '>=', $i]
                                ]);
                            });
                        })
                        ->where(['is_delete' => 0])
                        ->count();

                    //获取上期余额
                    $setDate = date('Y-m-d',$yesterday);
                    $lastDate = date('Y-m-d',($yesterday - 24 * 3600));
                    $bonus = \app\common\model\order\CloudBonusRecord::where(['date_record'=>$lastDate,'type'=> 1 + $i])->field('all_bonus,now_bonus,write_bonus')->order('create_time','desc')->find();
                    $lastBonus = is_null($bonus) ? 0 : $bonus['all_bonus'] - $bonus['write_bonus'];
                    //设置使用经销权分红数
                    $dividendNum = 0;
                    $dividendTotalNum = 0;
                    $dividendF = 1;
                    $dividendRatioF = $salespersonSet[$i]['dividend_ratio'];
                    if($salespersonNum > 0 && $dividendRatioF > 0){
                        $dividendTotalNum = $orderTotalPayPrice * $dividendF;
                        $dividendRatioF = min($dividendRatioF, 1);
                        //加上期余留
                        $dividendNum = ($dividendTotalNum + $lastBonus) * $dividendRatioF;
                        $salespersonSet[$i]['distribution'] =  $dividendNum / $salespersonNum;
                    }else{
                        $salespersonSet[$i]['distribution'] = 0;
                    }
                    //日志数据
                    $salespersonLogs[] = [
                        'date_record' => $setDate,
                        'last_bonus' => $lastBonus,
                        'now_bonus' => $dividendTotalNum,
                        'all_bonus' => ($dividendTotalNum + $lastBonus),
                        'write_bonus' => $dividendNum,
                        'write_bonus_ratio' => $dividendRatioF * 100,
                        'write_bonus_user' => $salespersonNum,
                        'total_sell_price' => $orderTotalPayPrice,
                        'type' => 1 + $i,
                        'start_date' => $setDate,
                        'end_date' => $setDate,
                    ];
                }

                //获取昨天确定收货销售总额
                $orderTotalPayPrice = \app\common\model\order\Order::query()
                    ->where('receipt_time', '>=', $startTime)
                    ->where('receipt_time', '<=', $endTime)
                    ->where(['order_status'=>30,'pay_status'=>20,'receipt_status'=>20])
                    ->whereIn('zone_type',[1])
                    ->sum('pay_price');
                //echo \app\common\model\order\Order::query()->getLastSql();
                /*echo $orderTotalPayPrice;
                exit;*/
                \think\facade\Db::startTrans();
                try {
                    foreach ($userList as $row) {
                        $userId = $row['user_id'];
                        $userLevel = $row['f_level'];
                        for($i = 1; $i <= $userLevel; $i++)
                        {
                            //设置分红
                            $dividend = $salespersonSet[$i]['distribution'];
                            //修改用户信息
                            \app\common\model\user\User::where('user_id', '=', $userId)->inc('voucher', $dividend)->update();
                            /*echo \app\common\model\user\User::getLastSql();
                            exit;*/
                            //记录日志
                            CloudBillRecord::create(['user_id' => $userId, 'price' => $dividend, 'ac_type' => 1, 'cur_type' => 2,
                                'profit' => $salespersonSet[$i]['name']."团队分红赠消费券", 'order_id' => 0, 'old_num' => $row['voucher'], 'new_num' => $row['voucher'] + $dividend,
                            ]);
                            \app\common\model\user\VoucherLog::add([
                                'user_id' => $userId,
                                'scene' => \app\common\enum\user\voucher\VoucherLogSceneEnum::TEAM_DIVIDEND,
                                'value' => $dividend,
                                'describe' => $salespersonSet[$i]['name'].'团队分红赠消费券',
                                'remark' => '',
                                'app_id' => $row['app_id'],
                            ]);
                        }
                    }
                    //记录团队分红日志
                    \app\common\model\order\CloudBonusRecord::insertAll($salespersonLogs);
                    \think\facade\Db::commit();
                } catch (\Exception $e) {
                    // 发生异常则回滚
                    \think\facade\Db::rollback();
                    var_dump(date('Y-m-d H:i:s', time()) . '团队执行分红错误:' . $e->getMessage());
                }
            }
            var_dump('团队分红执行结束');
        }
    }


    //获取用户小区业绩
    private  function getPlacementTotalPrice($userId,$zoneTypes=[1], $userList = [], $userPaySumList = [], $isDelMax = false, $isContainUserId = true){
        $totalPrice = 0;
        if($userList){
            $userIds = [];
            foreach ($userList as $row){
                if($row['referee_id'] == $userId){
                    $userIds[] = $row['user_id'];
                }
            }
        }else {
            $userIds = \app\common\model\user\User::where(['is_delete' => 0,'referee_id' => $userId])->column('user_id');
        }
        if(!$userIds){
            return $totalPrice;
        }
        //获取自己所有下级叠加的业绩
        $userTotalPrice = $this->findRefereeDown($userIds,$zoneTypes, $userList, $userPaySumList, $isContainUserId);
        //var_dump($userId.'-------------------'.count($userIds));
        //var_dump($userTotalPrice);
        //获取自己小区业绩
        //小区指业绩：去掉自己下级一名最多确定收货总金额，
        $isFind = 0;
        $maxPrice = max($userTotalPrice);
        foreach ($userTotalPrice as $price){
            //去除最大
            if($isDelMax && $isFind == 0 && $price == $maxPrice){
                $isFind = 1;
                continue;
            }
            $totalPrice += $price;
        }
        return $totalPrice;
    }

    //获取自己所有下级叠加的业绩
    private function findRefereeDown($userIds,$zoneTypes=[1], $userList = [], $userPaySumList = [], $isContainUserId = true)
    {
        $userTotalPrice = [];
        //var_dump(count($userIds).'========-----------------------------');
        //获取所有下级
        foreach ($userIds as $userId){
            $findUserIds = $this->findRefereeDownUser($userId,[],0,$isContainUserId, $userList);
            //获取总金额
            if($userPaySumList){
                $totalPrice = 0;
                foreach ($userPaySumList as $uId=>$price){
                    if(in_array($uId, $findUserIds)){
                        $totalPrice += $price;
                    }
                }
                $userTotalPrice[$userId] = $totalPrice;
            }else{
                $userTotalPrice[$userId] = \app\common\model\order\Order::query()
                    ->whereIn('user_id', $findUserIds)
                    ->where(['order_status'=>30,'pay_status'=>20,'receipt_status'=>20,'is_delete'=>0])
                    ->whereIn('zone_type',$zoneTypes)
                    ->sum('pay_price');
                #echo $userId.'='.\app\common\model\order\Order::getLastSql();
            }
        }
        return $userTotalPrice;
    }

    //获取自己所有下级用户
    private function findRefereeDownUser($userId, $userIds = [], $num = 0, $isContainUserId = true, $userList = [])
    {
        if(in_array($userId, $userIds)){
            return $userIds;
        }
        if($userList){
            $userIdGroup = [];
            foreach ($userList as $row){
                if($row['referee_id'] == $userId){
                    $userIdGroup[] = $row['user_id'];
                }
            }
        }else{
            $userIdGroup = \app\common\model\user\User::where(['is_delete'=>0,'referee_id'=>$userId])->column('user_id');
        }
        //echo User::getLastSql();
        if($isContainUserId){
            $userIds[] = $userId;
        }
        if(!$userIdGroup){
            return $userIds;
        }
        //获取所有下级
        foreach ($userIdGroup as $userId){
            $userIds = $this->findRefereeDownUser($userId, $userIds, $num, $isContainUserId, $userList);
        }
        return $userIds;
    }

}
