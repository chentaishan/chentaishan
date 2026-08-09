<?php
declare(strict_types=1);

namespace app\job\command;

use app\common\service\hekangyuan\DigitalAssetService;
use app\common\service\hekangyuan\VoucherPoolService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 福满堂消费券公共池每日发放（建议 crontab 每日 0:10 执行，发放前一日底池）
 *   php think hekangyuan_voucher_dividend_daily
 *   php think hekangyuan_voucher_dividend_daily --app_id=10001
 *   php think hekangyuan_voucher_dividend_daily --stat_date=20260617
 */
class HekangyuanVoucherDividendDaily extends Command
{
    protected function configure()
    {
        $this->setName('hekangyuan_voucher_dividend_daily')
            ->setDescription('福满堂消费券公共池每日发放：白/黑/大众三档分配，受个人消费券包额度限制')
            ->addOption('app_id', null, Option::VALUE_OPTIONAL, '仅执行指定 app_id', 0)
            ->addOption('stat_date', null, Option::VALUE_OPTIONAL, '指定发放日YYYYMMDD(默认昨日)', 0);
    }

    protected function execute(Input $input, Output $output)
    {
        $service  = new VoucherPoolService();
        $appId    = (int)$input->getOption('app_id');
        $statDate = (int)$input->getOption('stat_date');
        $statDate = $statDate > 0 ? $statDate : null;

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 消费券池每日发放开始');

        // 数字资产币昨日价快照（每日一次，逐 app 内部处理）
        try {
            $snap = DigitalAssetService::snapshotDailyPrice();
            $output->writeln('数字资产昨日价快照完成，配置数=' . $snap);
        } catch (\Throwable $e) {
            $output->writeln('数字资产昨日价快照失败：' . $e->getMessage());
        }

        $appIds = $appId > 0 ? [$appId] : $this->listAppIds();
        foreach ($appIds as $aid) {
            $ret = $service->runDailyDividend((int)$aid, $statDate);
            $output->writeln(
                "app_id={$aid} status={$ret['status']} "
                . (isset($ret['actual_total'])
                    ? ('底池=' . $ret['pool_total'] . ' 实发=' . $ret['actual_total'] . ' 结转=' . $ret['carryover_next'])
                    : ($ret['remark'] ?? ''))
            );
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] 消费券池每日发放结束');
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
