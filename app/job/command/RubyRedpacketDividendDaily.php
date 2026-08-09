<?php
declare(strict_types=1);

namespace app\job\command;

use app\common\service\ruby\RedpacketPoolService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 红宝石红包池每日加权分红（建议 crontab 每日 0:10 执行，分发前一日进池）
 *   php think ruby_redpacket_dividend_daily
 *   php think ruby_redpacket_dividend_daily --app_id=10001
 *   php think ruby_redpacket_dividend_daily --stat_date=20260615
 */
class RubyRedpacketDividendDaily extends Command
{
    protected function configure()
    {
        $this->setName('ruby_redpacket_dividend_daily')
            ->setDescription('红宝石红包池每日加权分红：按昨日进池占比分发，个人封顶')
            ->addOption('app_id', null, Option::VALUE_OPTIONAL, '仅执行指定 app_id', 0)
            ->addOption('stat_date', null, Option::VALUE_OPTIONAL, '指定进池日YYYYMMDD(默认昨日)', 0);
    }

    protected function execute(Input $input, Output $output)
    {
        $service  = new RedpacketPoolService();
        $appId    = (int)$input->getOption('app_id');
        $statDate = (int)$input->getOption('stat_date');
        $statDate = $statDate > 0 ? $statDate : null;

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 红包池每日分红开始');

        $appIds = $appId > 0 ? [$appId] : $this->listAppIds();
        foreach ($appIds as $aid) {
            $ret = $service->runDailyDividend((int)$aid, $statDate);
            $output->writeln(
                "app_id={$aid} status={$ret['status']} "
                . (isset($ret['dividend_amount']) ? ('发放=' . $ret['dividend_amount'] . ' 人数=' . ($ret['paid_users'] ?? 0)) : ($ret['remark'] ?? ''))
            );
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 红包池每日分红结束');
    }

    /** @return int[] */
    private function listAppIds(): array
    {
        try {
            $apps = Db::name('app')->where('is_delete', '=', 0)->where('is_recycle', '=', 0)->column('app_id');
            return array_map('intval', $apps);
        } catch (\Throwable $e) {
            return [0];
        }
    }
}
