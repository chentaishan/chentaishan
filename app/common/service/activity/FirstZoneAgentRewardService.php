<?php

namespace app\common\service\activity;

use think\facade\Db;

/**
 * 优品区(zone_type=1)：按收货地址匹配省/市/区代，pay_price × cloud_ratio 比例
 * - 支付成功：登记待结算并立即释放入余额（release_trigger=pay）
 * 比例 ratio_id：10=省%、11=市%、12=区%（jjjshop_cloud_ratio.num，status=0 启用）
 */
class FirstZoneAgentRewardService
{
    private const TRIGGER_PAY = 'pay';

    /** @var ActivityRewardService */
    private $rewardService;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    public function onPaySuccess(array $order): void
    {
        if (!$this->isFirstZoneAgentEnabled($order)) {
            return;
        }

        Db::transaction(function () use ($order) {
            $this->registerRegionAgentPendingRewards($order, 'pay');
        });
    }

    public function onOrderCompleted(array $order): void
    {
        // 优品区代理奖已改为支付成功发放，确认收货不再处理
    }

    private function isFirstZoneAgentEnabled(array $order): bool
    {
        if ((int)($order['zone_type'] ?? 0) !== ActivityRewardService::ZONE_FIRST) {
            return false;
        }
        if (!$this->rewardService->supportsSchema()) {
            $this->mlog('[优品区代理奖] activity_reward_log 未就绪，跳过');
            return false;
        }
        if (!config('wz_reward.first_zone_agent.enabled', true)
            || !config('wz_reward.first_zone_agent.region_enabled', true)) {
            return false;
        }
        return true;
    }

    private function registerRegionAgentPendingRewards(array $order, string $phase): void
    {
        $orderId    = (int)$order['order_id'];
        $fromUserId = (int)$order['user_id'];
        $zoneType   = ActivityRewardService::ZONE_FIRST;
        $appId      = (int)($order['app_id'] ?? 0);
        $payPrice   = round((float)($order['pay_price'] ?? 0), 2);

        if ($payPrice <= 0) {
            $this->mlog("[优品区代理奖|{$phase}] order_id={$orderId} pay_price=0 跳过");
            return;
        }

        if (!$this->hasTable('order_address')) {
            $this->mlog("[优品区代理奖|{$phase}] order_id={$orderId} 无 order_address 表");
            return;
        }

        $address = Db::name('order_address')->where('order_id', '=', $orderId)->find();
        if (!$address) {
            $this->mlog("[优品区代理奖|{$phase}] order_id={$orderId} 无收货地址");
            return;
        }

        $provinceId = (int)($address['province_id'] ?? 0);
        $cityId     = (int)($address['city_id'] ?? 0);
        $regionId   = (int)($address['region_id'] ?? 0);

        $ratioCfg = config('wz_reward.first_zone_agent.ratio_id', []);
        $ratioDef = config('wz_reward.first_zone_agent.ratio_defaults', []);

        $provinceRatio = $this->rewardService->getCloudRatioNum(
            (int)($ratioCfg['province'] ?? 10),
            (float)($ratioDef['province'] ?? 6)
        );
        $cityRatio = $this->rewardService->getCloudRatioNum(
            (int)($ratioCfg['city_total'] ?? 11),
            (float)($ratioDef['city_total'] ?? 5)
        );
        $districtRatio = $this->rewardService->getCloudRatioNum(
            (int)($ratioCfg['district'] ?? 12),
            (float)($ratioDef['district'] ?? 4)
        );

        $this->mlog("[优品区代理奖|{$phase}] order_id={$orderId} pay_price={$payPrice} 省%={$provinceRatio}(id10) 市%={$cityRatio}(id11) 区%={$districtRatio}(id12) addr={$provinceId}/{$cityId}/{$regionId}");

        // 区代：pay_price × 区%（无区代或比例为 0 不发）
        if ($regionId > 0 && $districtRatio > 0) {
            $agents = $this->findDistrictAgents($provinceId, $cityId, $regionId, $orderId);
            $pool   = round($payPrice * $districtRatio / 100, 2);
            $this->registerAgentPendingItems($agents, $pool, WzRewardRemarkHelper::regionRewardRemark($zoneType, 'district'), 'region_district', 'wz_first_district_', $orderId, $fromUserId, $zoneType, $appId, $phase);
        } elseif ($districtRatio > 0) {
            $this->mlog("[优品区代理奖|{$phase}] order_id={$orderId} 有区%但 region_id=0，未登记区代奖");
        }

        // 市代：pay_price × 市%（仅 agent_district_id=0 的市代账号）
        if ($cityId > 0 && $cityRatio > 0) {
            $agents = $this->findCityAgents($provinceId, $cityId, $orderId);
            $pool   = round($payPrice * $cityRatio / 100, 2);
            $this->registerAgentPendingItems($agents, $pool, WzRewardRemarkHelper::regionRewardRemark($zoneType, 'city'), 'region_city', 'wz_first_city_', $orderId, $fromUserId, $zoneType, $appId, $phase);
        }

        // 省代：pay_price × 省%（仅未设市、区的省代账号）
        if ($provinceId > 0 && $provinceRatio > 0) {
            $agents = $this->findProvinceAgents($provinceId, $orderId);
            $pool   = round($payPrice * $provinceRatio / 100, 2);
            $this->registerAgentPendingItems($agents, $pool, WzRewardRemarkHelper::regionRewardRemark($zoneType, 'province'), 'region_province', 'wz_first_province_', $orderId, $fromUserId, $zoneType, $appId, $phase);
        }

        $this->mlog("[优品区代理奖|{$phase}] 完成 order_id={$orderId}");
    }

    private function registerAgentPendingItems(
        array $agents,
        float $poolAmount,
        string $remark,
        string $scene,
        string $uniquePrefix,
        int $orderId,
        int $fromUserId,
        int $zoneType,
        int $appId,
        string $phase
    ): void {
        if (empty($agents) || $poolAmount <= 0) {
            return;
        }
        $each = round($poolAmount / count($agents), 2);
        foreach ($agents as $agent) {
            $agentUserId = (int)$agent['user_id'];
            if ($agentUserId <= 0 || $each <= 0) {
                continue;
            }
            $this->rewardService->createPendingRewardLogPublic([
                'unique_key'      => $uniquePrefix . $orderId . '_' . $agentUserId,
                'user_id'         => $agentUserId,
                'from_user_id'    => $fromUserId,
                'order_id'        => $orderId,
                'zone_type'       => $zoneType,
                'scene'           => $scene,
                'asset_type'      => 'balance',
                'amount'          => $each,
                'reward_month'    => date('Ym'),
                'release_trigger' => self::TRIGGER_PAY,
                'remark'          => $remark,
                'app_id'          => $appId,
            ]);
            $this->mlog("[优品区代理奖|{$phase}] {$remark} user_id={$agentUserId} amount={$each} 已登记待结算");
        }
    }

    private function findDistrictAgents(int $provinceId, int $cityId, int $regionId, int $orderId): array
    {
        $list = Db::name('user')
            ->where('agent_district_id', '=', $regionId)
            ->where('is_delete', '=', 0)
            ->select()
            ->toArray();
        $matched = array_values(array_filter($list, function ($u) use ($provinceId, $cityId) {
            return $this->agentRegionMatchesAddress($u, $provinceId, $cityId);
        }));
        if (empty($matched)) {
            $this->mlog("[优品区代理奖|区代] order_id={$orderId} region_id={$regionId} 候选=" . count($list) . " 匹配=0");
        }
        return $matched;
    }

    private function findCityAgents(int $provinceId, int $cityId, int $orderId): array
    {
        $list = Db::name('user')
            ->where('agent_city_id', '=', $cityId)
            ->where('agent_district_id', '=', 0)
            ->where('is_delete', '=', 0)
            ->select()
            ->toArray();
        $matched = array_values(array_filter($list, function ($u) use ($provinceId, $cityId) {
            return $this->agentRegionMatchesAddress($u, $provinceId, $cityId);
        }));
        if (empty($matched)) {
            $this->mlog("[优品区代理奖|市代] order_id={$orderId} city_id={$cityId} 候选=" . count($list) . " 匹配=0");
        }
        return $matched;
    }

    private function findProvinceAgents(int $provinceId, int $orderId): array
    {
        $list = Db::name('user')
            ->where('agent_province_id', '=', $provinceId)
            ->where('agent_city_id', '=', 0)
            ->where('agent_district_id', '=', 0)
            ->where('is_delete', '=', 0)
            ->select()
            ->toArray();
        if (empty($list)) {
            $this->mlog("[优品区代理奖|省代] order_id={$orderId} province_id={$provinceId} 无省代账号");
        }
        return $list;
    }

    private function agentRegionMatchesAddress(array $user, int $provinceId, int $cityId): bool
    {
        $userProvince = (int)($user['agent_province_id'] ?? 0);
        $userCity     = (int)($user['agent_city_id'] ?? 0);
        if ($userProvince > 0 && $provinceId > 0 && $userProvince !== $provinceId) {
            return false;
        }
        if ($userCity > 0 && $cityId > 0 && $userCity !== $cityId) {
            return false;
        }
        return true;
    }

    private function hasTable(string $shortName): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $shortName;
            return !empty(Db::query("SHOW TABLES LIKE '" . addslashes($fullName) . "'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'wz_zone_reward_debug.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
