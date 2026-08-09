<?php

namespace app\common\service\ruby;

use think\facade\Db;

/**
 * 红宝石：团队人数(team_count) 与 蓝宝石等级(sapphire_level) 维护
 *
 * 定级依据：整个下线团队人数（含自身，且须爆单区支付成功 is_burst_buyer=1）。
 * - 用户首次爆单支付成功：自身及全部上线 team_count +1，并各自重算等级。
 * - 改推荐人：对新旧上线链做全量重算（重新统计达标下线数）。
 */
class RubyTeamService
{
    private const MAX_DEPTH = 200;

    /**
     * 首次爆单支付成功：标记 is_burst_buyer，并让自身+全部上线 team_count+1、重算等级。
     * 幂等：已是 burst_buyer 则不重复累加。
     */
    public function onBurstPaid(int $userId, int $appId = 0): void
    {
        if ($userId <= 0) {
            return;
        }
        $user = Db::name('user')->where('user_id', '=', $userId)->find();
        if (!$user) {
            return;
        }
        if ((int)($user['is_burst_buyer'] ?? 0) === 1) {
            return;
        }

        Db::name('user')->where('user_id', '=', $userId)->update([
            'is_burst_buyer' => 1,
            'update_time'    => time(),
        ]);

        // 自身 + 全部上线 team_count + 1（自身也计入团队）
        $currentId = $userId;
        $visited   = [];
        $depth     = 0;
        while ($currentId > 0 && !isset($visited[$currentId]) && $depth++ < self::MAX_DEPTH) {
            $visited[$currentId] = true;
            Db::name('user')->where('user_id', '=', $currentId)->inc('team_count', 1)->update(['update_time' => time()]);
            $this->recomputeLevel($currentId);
            $currentId = (int)Db::name('user')->where('user_id', '=', $currentId)->value('referee_id');
        }
    }

    /**
     * 依据 team_count 重算并写入 sapphire_level（取满足阈值的最高等级）。
     */
    public function recomputeLevel(int $userId): int
    {
        $teamCount = (int)Db::name('user')->where('user_id', '=', $userId)->value('team_count');
        $level = $this->resolveLevelByTeamCount($teamCount);
        Db::name('user')->where('user_id', '=', $userId)->update([
            'sapphire_level' => $level,
            'update_time'    => time(),
        ]);
        return $level;
    }

    /**
     * 改推荐人后：对受影响的新旧上线链做全量重算（重新统计达标下线数）。
     *
     * @param int $userId       被改推荐人的用户
     * @param int $oldRefereeId 旧上级
     * @param int $newRefereeId 新上级
     */
    public function onRefereeChanged(int $userId, int $oldRefereeId, int $newRefereeId): void
    {
        $chains = [];
        foreach ([$userId, $oldRefereeId, $newRefereeId] as $start) {
            foreach ($this->collectUplineChain($start) as $uid) {
                $chains[$uid] = true;
            }
        }
        foreach (array_keys($chains) as $uid) {
            $this->recomputeTeamCountFull($uid);
            $this->recomputeLevel($uid);
        }
    }

    /**
     * 全量统计某用户团队人数 = 自身(若达标) + 全部达标下线，并写回 team_count。
     */
    public function recomputeTeamCountFull(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $count = $this->countQualifiedTeam($userId);
        Db::name('user')->where('user_id', '=', $userId)->update([
            'team_count'  => $count,
            'update_time' => time(),
        ]);
        return $count;
    }

    /**
     * BFS 统计：自身 + 全部下线中 is_burst_buyer=1 的人数。
     */
    private function countQualifiedTeam(int $rootUserId): int
    {
        $count = 0;
        $self  = Db::name('user')->where('user_id', '=', $rootUserId)->value('is_burst_buyer');
        if ((int)$self === 1) {
            $count++;
        }
        $frontier = [$rootUserId];
        $visited  = [$rootUserId => true];
        $depth    = 0;
        while (!empty($frontier) && $depth++ < self::MAX_DEPTH) {
            $children = Db::name('user')
                ->whereIn('referee_id', $frontier)
                ->field(['user_id', 'is_burst_buyer'])
                ->select()
                ->toArray();
            $next = [];
            foreach ($children as $child) {
                $cid = (int)$child['user_id'];
                if (isset($visited[$cid])) {
                    continue;
                }
                $visited[$cid] = true;
                if ((int)$child['is_burst_buyer'] === 1) {
                    $count++;
                }
                $next[] = $cid;
            }
            $frontier = $next;
        }
        return $count;
    }

    /**
     * @return int[] 从 $startUserId 起向上的所有上线 user_id（含自身）
     */
    private function collectUplineChain(int $startUserId): array
    {
        $chain   = [];
        $current = $startUserId;
        $visited = [];
        $depth   = 0;
        while ($current > 0 && !isset($visited[$current]) && $depth++ < self::MAX_DEPTH) {
            $visited[$current] = true;
            $chain[] = $current;
            $current = (int)Db::name('user')->where('user_id', '=', $current)->value('referee_id');
        }
        return $chain;
    }

    /**
     * team_count -> 蓝宝石等级（满足阈值的最高等级）
     */
    public function resolveLevelByTeamCount(int $teamCount): int
    {
        $rows = $this->getSapphireConfig();
        $level = 0;
        foreach ($rows as $row) {
            if ($teamCount >= (int)$row['fans_threshold']) {
                $level = max($level, (int)$row['level']);
            }
        }
        return $level;
    }

    /**
     * @return array<int, array{level:int,fans_threshold:int,reward_percent:float}>
     */
    public function getSapphireConfig(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $cache = Db::name('sapphire_config')
                ->where('status', '=', 0)
                ->order('level', 'asc')
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    /**
     * 等级对应奖励百分比
     */
    public function getRewardPercentByLevel(int $level): float
    {
        if ($level <= 0) {
            return 0.0;
        }
        foreach ($this->getSapphireConfig() as $row) {
            if ((int)$row['level'] === $level) {
                return (float)$row['reward_percent'];
            }
        }
        return 0.0;
    }
}
