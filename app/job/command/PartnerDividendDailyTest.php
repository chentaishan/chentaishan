<?php
declare(strict_types=1);

namespace app\job\command;

use app\common\service\activity\PartnerDividendDailyService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 代理进货区合伙人每日分红（测试，可同日多次重复执行）
 * 条件：进货区、已支付、已收货、未分红(partner_dividend_settled=0)、收货时间<当前时刻
 *
 * php think partner_dividend_daily_test
 * php think partner_dividend_daily_test --app_id=10001
 */
class PartnerDividendDailyTest extends Command
{
    protected function configure()
    {
        $this->setName('partner_dividend_daily_test')
            ->setDescription('【测试】合伙人每日分红：可多次执行，每单仅分红一次(partner_dividend_settled=0)')
            ->addOption('app_id', null, Option::VALUE_OPTIONAL, '仅执行指定 app_id', 0);
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new PartnerDividendDailyService();
        $appId   = (int)$input->getOption('app_id');

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 合伙人每日分红【测试】开始（截止=当前时刻，可重复）');

        if ($appId > 0) {
            $ret = $service->runForApp($appId, null, true);
            $output->writeln("app_id={$appId} " . ($ret['ok'] ? 'OK' : 'FAIL') . ' ' . $ret['msg']);
        } else {
            foreach ($service->runAllApps(null, true) as $row) {
                $output->writeln("app_id={$row['app_id']} " . ($row['ok'] ? 'OK' : 'SKIP/FAIL') . ' ' . $row['msg']);
            }
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 合伙人每日分红【测试】结束');
    }
}
