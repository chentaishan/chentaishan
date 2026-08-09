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
 * 代理进货区合伙人每日分红（建议 crontab 每日 0:05 执行）
 * php think partner_dividend_daily
 * php think partner_dividend_daily --app_id=10001
 */
class PartnerDividendDaily extends Command
{
    protected function configure()
    {
        $this->setName('partner_dividend_daily')
            ->setDescription('代理进货区合伙人每日分红：统计0点前已收货未分红订单，奖金池均分合伙人')
            ->addOption('app_id', null, Option::VALUE_OPTIONAL, '仅执行指定 app_id', 0);
    }

    protected function execute(Input $input, Output $output)
    {
        /*$service = new PartnerDividendDailyService();
        $appId   = (int)$input->getOption('app_id');*/

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 合伙人每日分红开始');

        /*if ($appId > 0) {
            $ret = $service->runForApp($appId);
            $output->writeln("app_id={$appId} " . ($ret['ok'] ? 'OK' : 'FAIL') . ' ' . $ret['msg']);
        } else {
            foreach ($service->runAllApps() as $row) {
                $output->writeln("app_id={$row['app_id']} " . ($row['ok'] ? 'OK' : 'SKIP/FAIL') . ' ' . $row['msg']);
            }
        }*/
        //合伙人分红
        $this->partner_dividend();
        $output->writeln('[' . date('Y-m-d H:i:s') . '] 合伙人每日分红结束');
    }

    //用合伙人分红定时任务
    public static function partner_dividend()
    {
        //== 每天统计，今天统计昨天==========================================
        $type = 1;
        $execNum = \app\common\model\order\CloudBonusRecord::where(['type'=>$type])->where('create_time','>=',date('Y-m-d 00:00:00'))->count();
        //$execNum = 0; //测试用
        if($execNum == 0){
            $yesterday = strtotime("-1 day");
            //测试用 上一期
            //$yesterday = strtotime("-2 day");
            //$yesterday = time(); //测试用
            $startTime = strtotime(date('Y-m-d 00:00:00',$yesterday)); // 昨天开始时间
            $endTime = strtotime(date('Y-m-d 23:59:59',$yesterday)); // 昨天结束时间
            var_dump("合伙人分红订单确认收货时间: ".date('Y-m-d H:i:s',$startTime).'-'.date('Y-m-d H:i:s',$endTime));
            // 获取合伙人分红分红比例
            $ratioF = \app\common\model\order\CloudRatio::where('ratio_id',80)->column('num','ratio_id');
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
            //获取每个等级分红人数
            //用户信息
            $userList = \app\common\model\user\User::where(['is_delete'=>0,'is_partner'=>1,'is_partner_out'=>0])->field('user_id,app_id,voucher,partner_dividend,partner_dividend_out')->select();
            $salespersonNum = $userList->count();
            if($salespersonNum > 0){
                //获取上期余额
                $setDate = date('Y-m-d',$yesterday);
                $lastDate = date('Y-m-d',($yesterday - 24 * 3600));
                $bonus = \app\common\model\order\CloudBonusRecord::where(['date_record'=>$lastDate,'type'=>$type])->field('all_bonus,now_bonus,write_bonus')->order('create_time','desc')->find();
                $lastBonus = is_null($bonus) ? 0 : $bonus['all_bonus'] - $bonus['write_bonus'];
                //设置使用经销权分红数
                $dividendRatioF = 1;
                $dividendF = ($ratioF && isset($ratioF[80]) && $ratioF[80] > 0 ? $ratioF[80] / 100 : 0);
                $dividendTotalNum = $orderTotalPayPrice * $dividendF;
                //加上期余留
                $dividendNum = ($dividendTotalNum + $lastBonus) * $dividendRatioF;
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
                    'type' => $type,
                    'start_date' => $setDate,
                    'end_date' => $setDate,
                ];
                //分红
                $dividend = $dividendNum / $salespersonNum;
                \think\facade\Db::startTrans();
                try {
                    foreach ($userList as $row){
                        $userId = $row['user_id'];
                        //设置出局分红
                        $dividendOut = $dividend;
                        $updateData = ['is_partner_out'=>0];
                        if($row['partner_dividend'] + $dividendOut >= $row['partner_dividend_out']){
                            $updateData = ['is_partner_out'=>1];
                            $dividendOut = $row['partner_dividend_out'] - $row['partner_dividend'];
                        }
                        //修改用户信息
                        \app\common\model\user\User::where('user_id', '=', $userId)
                            ->inc('voucher', $dividend)
                            ->inc('partner_dividend', $dividendOut)
                            ->update($updateData);
                        /*echo \app\common\model\user\User::getLastSql();
                        exit;*/
                        //记录日志
                        CloudBillRecord::create(['user_id' => $userId,'price' => $dividend, 'ac_type' => 1, 'cur_type' => 2,
                            'profit' => "合伙人分红赠积分",'order_id' => 0, 'old_num' => $row['voucher'], 'new_num' => $row['voucher'] + $dividend,
                        ]);
                        \app\common\model\user\VoucherLog::add([
                            'user_id'  => $userId,
                            'scene'    => \app\common\enum\user\voucher\VoucherLogSceneEnum::PARTNER_DIVIDEND,
                            'value'    => $dividend,
                            'describe' => '合伙人分红赠积分',
                            'remark'   => '',
                            'app_id'   => $row['app_id'],
                        ]);
                    }
                    //记录合伙人分红日志
                    \app\common\model\order\CloudBonusRecord::insertAll($salespersonLogs);
                    \think\facade\Db::commit();
                } catch (\Exception $e) {
                    // 发生异常则回滚
                    \think\facade\Db::rollback();
                    var_dump(date('Y-m-d H:i:s', time()) . '合伙人执行分红错误:' . $e->getMessage());
                }
            }
            var_dump('合伙人分红执行结束');
        }
    }

}
