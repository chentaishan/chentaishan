<?php
declare(strict_types=1);

namespace app\job\command;

use app\common\service\activity\ActivityRewardService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 筛查并补发优品区跨版本漏奖订单（幂等）
 *
 * 仅列出（默认优品区 + 已收货漏发 + 未收货漏发）：
 *   php think repair_missed_rewards --deploy_at="2026-06-08 12:00:00"
 *
 * 仅查「上线前支付、仍未收货」：
 *   php think repair_missed_rewards --deploy_at="2026-06-08 12:00:00" --scope=unreceived
 *
 * 执行补发：
 *   php think repair_missed_rewards --deploy_at="2026-06-08 12:00:00" --execute
 *
 * 指定单笔：
 *   php think repair_missed_rewards --order_id=10001 --execute
 */
class RepairMissedRewards extends Command
{
    protected function configure()
    {
        $this->setName('repair_missed_rewards')
            ->setDescription('筛查/补发优品区跨版本漏奖订单（幂等）')
            ->addOption('deploy_at', null, Option::VALUE_REQUIRED, '代码上线时间，如 2026-06-08 12:00:00')
            ->addOption('zone_type', null, Option::VALUE_OPTIONAL, '筛查分区：1=优品区(默认) 2/3=其他 0=全部', 1)
            ->addOption('scope', null, Option::VALUE_OPTIONAL, 'received=上线后已收货 unreceived=未收货 all=全部(默认)', 'all')
            ->addOption('order_id', null, Option::VALUE_OPTIONAL, '指定订单 ID 补发', 0)
            ->addOption('limit', null, Option::VALUE_OPTIONAL, '最多筛查条数', 500)
            ->addOption('execute', null, Option::VALUE_NONE, '执行补发（默认仅列出）');
    }

    protected function execute(Input $input, Output $output)
    {
        $service  = new ActivityRewardService();
        $orderId  = (int)$input->getOption('order_id');
        $execute  = (bool)$input->getOption('execute');
        $zoneType = (int)$input->getOption('zone_type');
        $scope    = strtolower(trim((string)$input->getOption('scope')));
        $limit    = max(1, (int)$input->getOption('limit'));

        if ($orderId > 0) {
            $this->repairOne($service, $orderId, $execute, $output);
            return;
        }

        $deployAt = trim((string)$input->getOption('deploy_at'));
        if ($deployAt === '') {
            $output->writeln('<error>请传 --deploy_at 或 --order_id</error>');
            return;
        }
        $deployTs = strtotime($deployAt);
        if ($deployTs === false) {
            $output->writeln('<error>deploy_at 格式无效</error>');
            return;
        }

        $rows = $service->findCrossVersionRepairCandidates($deployTs, $zoneType, $limit, $scope);
        $output->writeln(sprintf(
            '[%s] 上线时间=%s zone_type=%s scope=%s 疑似漏发 %d 单%s',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s', $deployTs),
            $zoneType > 0 ? (string)$zoneType : 'all',
            in_array($scope, ['all', 'received', 'unreceived'], true) ? $scope : 'all',
            count($rows),
            $execute ? '，开始补发' : '（dry-run，加 --execute 执行）'
        ));

        foreach ($rows as $row) {
            $line = sprintf(
                'order_id=%s order_no=%s zone_type=%s scenario=%s user_id=%s pay=%s pay_at=%s receipt_status=%s receipt_at=%s',
                $row['order_id'],
                $row['order_no'] ?? '',
                $row['zone_type'] ?? 0,
                $row['miss_scenario'] ?? '-',
                $row['user_id'] ?? 0,
                $row['pay_price'] ?? 0,
                !empty($row['pay_time']) ? date('Y-m-d H:i:s', (int)$row['pay_time']) : '-',
                $row['receipt_status'] ?? '-',
                !empty($row['receipt_time']) ? date('Y-m-d H:i:s', (int)$row['receipt_time']) : '-'
            );
            if (!$execute) {
                $output->writeln($line);
                continue;
            }
            $orderId = (int)$row['order_id'];
            $ok = $service->repairMissedOrderRewards($orderId);
            if ($ok) {
                $output->writeln($line . ' => OK');
                continue;
            }
            $failReason = $service->getLastRepairError() ?: '未知原因';
            $output->writeln($line . ' => FAIL: ' . $failReason);
        }
    }

    private function repairOne(ActivityRewardService $service, int $orderId, bool $execute, Output $output): void
    {
        $reason = $service->explainRepairFailure($orderId);
        if (!$execute) {
            $output->writeln("order_id={$orderId} dry-run（加 --execute 执行补发）");
            if ($reason !== '') {
                $output->writeln("<comment>预检: {$reason}</comment>");
            }
            return;
        }
        $ok = $service->repairMissedOrderRewards($orderId);
        if ($ok) {
            $output->writeln("order_id={$orderId} OK");
            return;
        }
        $failReason = $service->getLastRepairError() ?: $reason ?: '未知原因';
        $output->writeln("<error>order_id={$orderId} FAIL: {$failReason}</error>");
    }
}
