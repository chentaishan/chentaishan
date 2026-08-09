<?php

namespace app\common\service\activity;

use app\common\model\order\Order as OrderModel;

/**
 * 业绩统计：与奖励发放口径一致
 * - 优品区(zone_type=1)：支付成功即计入
 * - 其余分区：确认收货后计入
 */
class WzPerformanceService
{
    public const ZONE_FIRST = ActivityRewardService::ZONE_FIRST;

    /**
     * @param list<int> $userIds
     */
    public static function sumUserPerformance(
        array $userIds,
        ?int $zoneType = null,
        ?int $timeStart = null,
        ?int $timeEnd = null
    ): float {
        $userIds = self::normalizeUserIds($userIds);
        if (empty($userIds)) {
            return 0.0;
        }

        if ($zoneType === self::ZONE_FIRST) {
            return self::sumFirstZonePerformance($userIds, $timeStart, $timeEnd);
        }

        if ($zoneType !== null) {
            return self::sumReceiptZonePerformance($userIds, $zoneType, $timeStart, $timeEnd);
        }

        return round(
            self::sumFirstZonePerformance($userIds, $timeStart, $timeEnd)
            + self::sumReceiptZonePerformance($userIds, null, $timeStart, $timeEnd),
            2
        );
    }

    /**
     * @param list<int> $userIds
     */
    private static function sumFirstZonePerformance(array $userIds, ?int $timeStart, ?int $timeEnd): float
    {
        $query = OrderModel::whereIn('user_id', $userIds)
            ->where('pay_status', '=', 20)
            ->where('zone_type', '=', self::ZONE_FIRST);
        if ($timeStart !== null && $timeEnd !== null) {
            $query->whereBetween('pay_time', [$timeStart, $timeEnd]);
        }
        return (float)$query->sum('total_price');
    }

    /**
     * @param list<int> $userIds
     */
    private static function sumReceiptZonePerformance(
        array $userIds,
        ?int $zoneType,
        ?int $timeStart,
        ?int $timeEnd
    ): float {
        $query = OrderModel::whereIn('user_id', $userIds)
            ->where('pay_status', '=', 20)
            ->where('receipt_status', '=', 20);
        if ($zoneType !== null) {
            $query->where('zone_type', '=', $zoneType);
        } else {
            $query->where('zone_type', '<>', self::ZONE_FIRST);
        }
        if ($timeStart !== null && $timeEnd !== null) {
            $query->whereBetween('receipt_time', [$timeStart, $timeEnd]);
        }
        return (float)$query->sum('total_price');
    }

    /**
     * @param list<int|string> $userIds
     * @return list<int>
     */
    private static function normalizeUserIds(array $userIds): array
    {
        $normalized = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId > 0) {
                $normalized[] = $userId;
            }
        }
        return array_values(array_unique($normalized));
    }
}
