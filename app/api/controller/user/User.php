<?php

namespace app\api\controller\user;

use app\api\controller\product\Product;
use app\api\model\user\User as UserModel;
use app\api\controller\Controller;
use app\api\model\settings\Setting as SettingModel;
use app\common\library\easywechat\AppWx;
use app\common\service\activity\ActivityRewardService;
use think\facade\Db;

/**
 * 用户管理模型
 */
class User extends Controller
{
    /**
     * 用户自动登录,默认微信小程序
     */
    public function login()
    {
        $model = new UserModel;
        $userInfo = $model->login($this->request->post());
        return $this->renderSuccess('', [
            'user_id' => $userInfo['user_id'],
            'mobile' => $userInfo['mobile']
        ]);
    }

    /**
     * 有手机号用户登录
     */
    public function userLogin($code)
    {
        $model = new UserModel;
        $user_id = $model->userLogin($code);
        return $this->renderSuccess('', [
            'user_id' => $user_id,
            'token' => $model->getToken()
        ]);
    }

    /**
     * 当前用户详情
     */
    public function detail()
    {
        // 当前用户信息
        $userInfo = $this->getUser();
        $gift_name = SettingModel::getItem('live')['gift_name'];
        return $this->renderSuccess('', compact('userInfo', 'gift_name'));
    }

    public function getSession($code)
    {
        // 微信登录 获取session_key
        $app = AppWx::getApp();
        $session_key = null;
        $session = AppWx::sessionKey($app, $code);
        if ($session != null) {
            $session_key = $session['session_key'];
        }
        return $this->renderSuccess('', compact('session_key'));
    }

    /**
     * 绑定手机号
     */
    public function bindMobile()
    {
        $model = (new UserModel());
        $user_id = $model->bindMobile($this->request->post());
        if ($user_id) {
            return $this->renderSuccess('', [
                'token' => $model->getToken(),
                'user_id' => $user_id
            ]);
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     * 修改用户信息
     */
    public function updateInfo()
    {
        // 当前用户信息
        $model = $this->getUser();
        if ($model->edit($this->request->post())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     * 积分转换余额
     */
    public function transPoints($points = 0)
    {
        // 当前用户信息
        $model = $this->getUser();
        if ($model->transPoints($points)) {
            return $this->renderSuccess('转换成功');
        }
        return $this->renderError($model->getError() ?: '转换失败');
    }

    /**
     * 删除账号
     */
    /**
     * 用户余额互转
     */
    public function transferBalance()
    {
        $model = $this->getUser();
        $result = $model->transferBalance($this->request->post());
        if ($result !== false) {
            return $this->renderSuccess('转账成功', $result);
        }
        return $this->renderError($model->getError() ?: '转账失败');
    }

    /**
     * 修改支付密码
     */
    public function changePayPassword()
    {
        $model = $this->getUser();
        if ($model->changePayPassword($this->request->post())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }
    public function deleteAccount()
    {
        $model = new UserModel();
        if ($model->setDelete($this->getUser())) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    /**
     * 退出登录
     */
    public function logOut($token)
    {
        $model = $this->getUser();
        if ($model->logOut($token)) {
            return $this->renderSuccess('退出成功');
        }
        return $this->renderError($model->getError() ?: '退出失败');
    }

    //获取团队信息
    public function getTeam()
    {
        $param = $this->request->post();

        $pUser = \app\common\model\user\User::where(['user_id' => $this->getUser()['referee_id']])->where(['is_delete' => 0])->find();

        $data['p_name'] = $pUser['nickName'] ?? '';
        $data['p_mobile'] = $pUser['mobile'] ?? '';



        $data['list'] = \app\common\model\user\User::field('user_id,avatarUrl,nickName,mobile,referee_id')
            ->where('referee_id', $this->getUser()['user_id'])
            ->where(['is_delete' => 0])
            ->paginate($param)->each(function ($item) {
                $aUser = \app\common\model\user\User::where(['user_id' => $item['referee_id']])->find();

                // $item['a_name'] = $aUser['nickName'] ?? '';
                // $item['a_mobile'] = $aUser['mobile'] ?? '';
                // $item['performance'] = \app\common\model\order\Order::where('user_id',$item['user_id'])
                //     ->where('pay_status',20)
                //     ->where('zone_type',1)
                //     ->sum('pay_price');
                // return $item;

                $arr = [$item['user_id']];

                Product::display_all($item['user_id'],$arr,0);
                //$this->display_all($item['user_id'],$arr,0);
                $item['a_name'] = $aUser['nickName'] ?? '';
                $item['a_mobile'] = $aUser['mobile'] ?? '';
                $item['performance'] = \app\common\model\order\Order::whereIn('user_id',$arr)
                    ->where('pay_status',20)
                    //->where('zone_type',1)
                    ->sum('pay_price');
                return $item;
            });

        $data['num'] = \app\common\model\user\User::where('referee_id', $this->getUser()['user_id'])->where(['is_delete' => 0])->count();

        return $this->renderSuccess('', $data);
    }
    /**
     * 统计每月8号，18号，28号的订单数据
     * // 18 号的逻辑（假设今天是 3 月 18 日）
     * $startDate: 3 月 1 日 + 7 天 = 3 月 8 日 00:00:00
     * $endDate:   3 月 1 日 + 16 天 = 3 月 17 日 23:59:59
     *
     * // 28 号的逻辑（假设今天是 3 月 28 日）
     * $startDate: 3 月 1 日 + 17 天 = 3 月 18 日 00:00:00
     * $endDate:   3 月 1 日 + 26 天 = 3 月 27 日 23:59:59
     */
    public function runSettlement()
    {
        //每个月先从8号凌晨1点开始统计28号0点到7号24点的订单数据
        $user_id = 6;
        $now = new \DateTime();
        $currentDay = 8;

        // 定义结算日期常量
        $settlementDays = [8, 18, 28];
        // 如果不是结算日，提前返回
        if (!in_array($currentDay, $settlementDays)) {
            return $this->renderError('今天不是结算日，结算日为每月 8 号、18 号、28 号');
        }
        // 计算结算周期
        list($startDate, $endDate, $periodDesc) = $this->calculateSettlementPeriod($now, $currentDay);

        // 获取时间戳用于数据库查询
        $startTime = $startDate->getTimestamp();
        $endTime = $endDate->getTimestamp();
        dump($startTime);
        dump($endTime);die();
        // 获取分红比例配置
        $ratioConfig = Db::name('cloud_ratio')
            ->where('ratio_id', 11)
            ->where('status', '=', 0)
            ->value('num');
        $ratio = $ratioConfig / 100; // 转换为小数，如 30% = 0.3
        // 获取结算周期内的订单数据
        $orderAmount = Db::name('order')
            ->where('pay_status', 20)
            ->where('pay_time', '>=', $startTime)
            ->where('pay_time', '<=', $endTime)
            ->where('user_id', $user_id)
            ->sum('pay_price');
        // 统计总平台的订单金额
        $totalPlatformAmount = Db::name('order')
            ->where('pay_status', 20)
            ->sum('pay_price');
        return $this->renderSuccess('结算统计完成', [
            'period' => $periodDesc,
            'start_time' => date('Y-m-d H:i:s', $startTime),
            'end_time' => date('Y-m-d H:i:s', $endTime),
            'order_amount' => $orderAmount,
            'platform_amount' => $totalPlatformAmount,
            'ratio' => $ratio
        ]);


    }
    /**
     * 计算结算周期
     * @param \DateTime $now 当前时间
     * @param int $currentDay 当前日期（几号）
     * @return array [$startDate, $endDate, periodDesc]
     */
    private function calculateSettlementPeriod($now, $currentDay)
    {

        switch ($currentDay) {
            case 8:
                // 8 号：统计上月 28 号 0 点到本月 7 号 24 点
                $startDate = clone $now;
                $startDate->modify('last day of last month');
                $startDate->setDate((int)$startDate->format('Y'), (int)$startDate->format('m'), 28);
                $startDate->setTime(0, 0, 0);

                $endDate = clone $now;
                $endDate->setDate((int)$endDate->format('Y'), (int)$endDate->format('m'), 7);
                $endDate->setTime(23, 59, 59);

                $periodDesc = "上月 28 日至本月 7 日";
                break;

            case 18:
                // 18 号：统计本月 8 号 0 点到 17 号 24 点
                $startDate = clone $now;
                $startDate->setDate((int)$startDate->format('Y'), (int)$startDate->format('m'), 8);
                $startDate->setTime(0, 0, 0);

                $endDate = clone $now;
                $endDate->setDate((int)$endDate->format('Y'), (int)$endDate->format('m'), 17);
                $endDate->setTime(23, 59, 59);

                $periodDesc = "本月 8 日至 17 日";
                break;

            case 28:
                // 28 号：统计本月 18 号 0 点到 27 号 24 点
                $startDate = clone $now;
                $startDate->setDate((int)$startDate->format('Y'), (int)$startDate->format('m'), 18);
                $startDate->setTime(0, 0, 0);


                $endDate = clone $now;
                $endDate->setDate((int)$endDate->format('Y'), (int)$endDate->format('m'), 27);
                $endDate->setTime(23, 59, 59);
                $periodDesc = "本月 18 日至 27 日";
                break;

            default:
                throw new \InvalidArgumentException("无效的结算日期：{$currentDay}");
        }

        return [$startDate, $endDate, $periodDesc];
    }
    /**
     * 统计每日结算（时间00点到24点）根据订单
     */
    public function daily()
    {
        $date = date('Y-m-d', strtotime('-1 day'));
        $startTime = strtotime($date);
        $endTime = $startTime + 86400 - 1;
        print("统计时间范围：" . date('Y-m-d H:i:s', $startTime) . " 至 " . date('Y-m-d H:i:s', $endTime));

        // 获取所有有支付的订单（按用户分组）
        $userDayAmounts = Db::name('order')
            ->where('pay_status', 20)
            ->where('pay_time', '>=', $startTime)
            ->where('pay_time', '<=', $endTime)
            ->field('user_id, SUM(pay_price)')
            ->group('user_id')
            ->order('pay_time', 'desc') // 按支付时间倒序
            ->column('pay_price', 'user_id');
        // 如果没有订单，提前返回
        if (empty($userDayAmounts)) {
            return $this->renderSuccess('没有数据');
        }

        // 按用户分组统计订单金额
        $involvedUserIds = array_keys($userDayAmounts);
        $allUserIds = array_unique(array_merge(
            $involvedUserIds,
            array_values(Db::name('user')
                ->whereIn('user_id', $involvedUserIds)
                ->column('referee_id'))
        ));
        $allUserIds = array_filter($allUserIds, function($id) { return $id > 0; });

        // 一次性查出所有相关用户的关系
        $usersRelation = Db::name('user')
            ->whereIn('user_id', array_unique($allUserIds))
            ->field('user_id, referee_id')
            ->select()
            ->toArray();
        // 构建用户关系映射
        $userRefereeMap = []; // [userId => refereeId]
        $parentChildrenMap = []; // [parentId => [childId1, childId2, ...]]

        foreach ($usersRelation as $user) {
            $userId = intval($user['user_id']);
            $refereeId = intval($user['referee_id']);

            if ($refereeId > 0) {
                $userRefereeMap[$userId] = $refereeId;

                if (!isset($parentChildrenMap[$refereeId])) {
                    $parentChildrenMap[$refereeId] = [];
                }
                $parentChildrenMap[$refereeId][] = $userId;
            }
        }
        // 4. 执行多级分销统计计算（核心逻辑）
        $settlementResults = $this->calculateMultiLevelSettlement(
            $userDayAmounts,
            $userRefereeMap,
            $parentChildrenMap
        );
        // 5. 找出所有顶级父级（团长，没有上级）
        $topParents = $this->findTopParents(array_keys($settlementResults), $userRefereeMap);
        // 6. 保存统计结果并累加所有父级总额
        $totalSettleMoney = 0;
        $updateCount = 0;
        $parentTotalSum = 0; // 所有父级最终金额的总和

        foreach ($settlementResults as $userId => $result) {
            // 只有当用户有结算金额时才更新
            if ($result['final_amount'] > 0) {
                $totalSettleMoney += $result['final_amount'];
                $updateCount++;

                // 记录详细日志
                echo "[结算] 用户:{$userId} | 自身:{$result['self_amount']} | " .
                    "直接下级数:" . count($result['direct_children'] ?? []) . " | " .
                    "下级和:{$result['children_sum']} | 去最大:{$result['max_child']} | " .
                    "实发:{$result['final_amount']}" . PHP_EOL;
            }
        }
        $num = Db::name('cloud_ratio')->where('ratio_id',13)->where('status', '=', 0)->value('num');

        // 7. 计算奖励发放（父级总额的 30%）
        $rewardRate = $num / 100; // 奖励比例 30%
        $balanceRate = 0.80; // 余额比例 80%
        $pointsRate = 0.20; // 积分比例 20%
        $totalReward = bcmul($totalSettleMoney, $rewardRate, 2); // 总奖励金额，保留 2 位小数
        $balanceAmount = bcmul($totalSettleMoney, $balanceRate, 2); // 余额部分，保留 2 位小数
        $pointsAmount = bcmul($totalSettleMoney, $pointsRate, 2); // 积分部分，保留 2 位小数

        // 8. 将所有父级总额累加到顶级父级，并发放奖励
        $topParentUpdateCount = 0;
        $distributedUsers = [];
        foreach ($topParents as $topParentId) {
            if ($parentTotalSum > 0) {
                // 开启事务
                Db::startTrans();

                try {
                    // 发放余额奖励（80%）
                    if ($balanceAmount > 0) {
                        Db::name('user')
                            ->where('user_id', $topParentId)
                            ->inc('balance', $balanceAmount)
                            ->update();
                    }

                    // 发放积分奖励（20%）
                    if ($pointsAmount > 0) {
                        Db::name('user')
                            ->where('user_id', $topParentId)
                            ->inc('points', $pointsAmount)
                            ->update();
                    }

                    // 记录发放日志
//                    Db::name('settlement_log')->insert([
//                        'user_id' => $topParentId,
//                        'settlement_date' => $date,
//                        'parent_total_sum' => $parentTotalSum,
//                        'reward_rate' => $rewardRate,
//                        'total_reward' => $totalReward,
//                        'balance_amount' => $balanceAmount,
//                        'points_amount' => $pointsAmount,
//                        'balance_rate' => $balanceRate,
//                        'points_rate' => $pointsRate,
//                        'create_time' => date('Y-m-d H:i:s')
//                    ]);

                    Db::commit();

                    $topParentUpdateCount++;
                    $distributedUsers[] = $topParentId;

                    echo "[发放奖励] 团长:{$topParentId} | " .
                        "父级总额:{$parentTotalSum} | " .
                        "总奖励:{$totalReward} | " .
                        "余额:{$balanceAmount}(80%) | " .
                        "积分:{$pointsAmount}(20%)" . PHP_EOL;

                } catch (\Exception $e) {
                    Db::rollback();
                    throw $e;
                }
            }
        }
        // 9. 记录汇总日志
        echo "[汇总] 统计日期:{$date} | 结算用户数:{$updateCount} | " .
            "总金额:{$totalSettleMoney} | 父级总额:{$parentTotalSum} | " .
            "顶级父级数:{$topParentUpdateCount} | " .
            "发放总奖励:{$totalReward} | 余额:{$balanceAmount} | 积分:{$pointsAmount}"  . PHP_EOL;
    }
    //找出所有顶级父级（没有上级的用户，即团长）
    private function findTopParents($userIds, $userRefereeMap)
    {
        $topParents = [];

        foreach ($userIds as $userId) {
            // 检查该用户是否有上级
            $hasParent = isset($userRefereeMap[$userId]) && $userRefereeMap[$userId] > 0;

            if (!$hasParent) {
                // 没有上级，就是顶级父级（团长）
                $topParents[] = $userId;
            }
        }

        return array_unique($topParents);
    }
    /**
     * 获取拓扑排序的处理顺序（从无下级的用户开始，逐级向上）
     * @param array $userRefereeMap 用户上级映射
     * @param array $allUserIds 所有用户 ID
     * @return array 处理顺序
     */
    private function getTopologicalOrder($userRefereeMap, $allUserIds)
    {
        // 统计每个用户的入度（有多少个下级）
        $inDegree = [];
        $childrenCount = [];

        foreach ($allUserIds as $userId) {
            $inDegree[$userId] = 0;
            $childrenCount[$userId] = 0;
        }

        // 计算每个用户的下级数量
        foreach ($allUserIds as $userId) {
            $refereeId = $userRefereeMap[$userId] ?? 0;
            if ($refereeId > 0 && isset($childrenCount[$refereeId])) {
                $childrenCount[$refereeId]++;
            }
        }

        // 使用队列进行拓扑排序
        $queue = [];
        $result = [];

        // 先将所有没有下级的用户加入队列（叶子节点）
        foreach ($allUserIds as $userId) {
            if ($childrenCount[$userId] === 0) {
                $queue[] = $userId;
            }
        }

        while (!empty($queue)) {
            $current = array_shift($queue);
            $result[] = $current;

            // 处理当前用户的上级
            $refereeId = $userRefereeMap[$current] ?? 0;
            if ($refereeId > 0 && isset($inDegree[$refereeId])) {
                $childrenCount[$refereeId]--;
                if ($childrenCount[$refereeId] === 0) {
                    $queue[] = $refereeId;
                }
            }
        }

        // 如果还有未处理的用户（可能存在环路），添加到结果末尾
        $remainingUsers = array_diff($allUserIds, $result);
        if (!empty($remainingUsers)) {
            $result = array_merge($result, array_values($remainingUsers));
        }

        return $result;
    }
    /**
     * 计算多级分销结算金额（核心递归逻辑）
     * @param array $userDayAmounts 用户日订单金额 [userId => amount]
     * @param array $userRefereeMap 用户上级映射 [userId => refereeId]
     * @param array $parentDirectChildrenMap 父级直接下级映射 [parentId => [childId1, childId2, ...]]
     * @return array 结算结果
     */
    private function calculateMultiLevelSettlement($userDayAmounts, $userRefereeMap, $parentDirectChildrenMap)
    {
        $results = [];
        $processedUsers = []; // 记录已处理的用户，防止重复

        // 初始化所有涉及的用户
        $allParentIds = array_keys($parentDirectChildrenMap);
        $allUserIds = array_unique(array_merge(
            array_keys($userDayAmounts),
            $allParentIds
        ));

        foreach ($allUserIds as $userId) {
            $results[$userId] = [
                'self_amount' => floatval($userDayAmounts[$userId] ?? 0),
                'direct_children' => $parentDirectChildrenMap[$userId] ?? [],
                'children_sum' => 0,
                'max_child' => 0,
                'valid_children_sum' => 0,
                'final_amount' => 0
            ];
        }

        // 使用拓扑排序确定处理顺序（从无下级的用户开始向上处理）
        $processingOrder = $this->getTopologicalOrder($userRefereeMap, $allUserIds);

        // 按照处理顺序计算每个用户的最终金额
        foreach ($processingOrder as $userId) {
            if (!isset($results[$userId])) continue;

            $directChildren = $parentDirectChildrenMap[$userId] ?? [];

            if (empty($directChildren)) {
                // 没有下级，最终金额 = 自身订单金额
                $results[$userId]['final_amount'] = $results[$userId]['self_amount'];
                continue;
            }

            // 收集所有直接下级的最终金额
            $childFinalAmounts = [];
            foreach ($directChildren as $childId) {
                if (isset($results[$childId]['final_amount'])) {
                    $childFinalAmounts[] = $results[$childId]['final_amount'];
                }
            }

            if (empty($childFinalAmounts)) {
                // 下级都没有结算金额
                $results[$userId]['final_amount'] = $results[$userId]['self_amount'];
                continue;
            }

            // 计算直接下级总和
            $childrenSum = array_sum($childFinalAmounts);
            $maxChild = max($childFinalAmounts);
            $validChildrenSum = $childrenSum - $maxChild;

            $results[$userId]['children_sum'] = $childrenSum;
            $results[$userId]['max_child'] = $maxChild;
            $results[$userId]['valid_children_sum'] = $validChildrenSum;

            // 父级最终金额 = 自身订单 + (直接下级总和 - 最大直接下级)
            $results[$userId]['final_amount'] =
                $results[$userId]['self_amount'] + $validChildrenSum;
        }

        return $results;
    }

    /**
     * 解冻奖励
     */
    public function rewardStatus($user_id)
    {
        $model = \app\common\model\user\User::detail($user_id);
        if (!$model) {
            return $this->renderError('用户不存在');
        }
        $service = new ActivityRewardService();
        if ($service->adminSetRewardFreeze($user_id, 0)) {
            return $this->renderSuccess('解冻成功');
        }
        return $this->renderError('解冻失败，请检查数据库字段是否就绪');
    }

}