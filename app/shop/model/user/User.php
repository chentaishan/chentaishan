<?php

namespace app\shop\model\user;

use app\common\model\user\Grade as GradeModel;
use app\common\model\user\UserTag as UserTagModel;
use app\shop\model\user\GradeLog as GradeLogModel;
use app\shop\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\User as UserModel;
use app\common\enum\user\grade\ChangeTypeEnum;
use app\common\enum\user\balanceLog\BalanceLogSceneEnum as SceneEnum;
use app\shop\model\user\PointsLog as PointsLogModel;
use app\shop\model\plus\agent\User as AgentUserModel;
use app\common\service\activity\ActivityRewardService;
use app\common\service\activity\EnergyRewardService;
use think\facade\Db;
use app\common\model\user\VoucherLog as VoucherLogModel;
use app\common\enum\user\voucher\VoucherLogSceneEnum;

/**
 * 用户模型
 */
class User extends UserModel
{
    /**
     * 获取当前用户总数
     */
    public function getUserTotal($day = null)
    {
        $model = $this;
        if (!is_null($day)) {
            $startTime = strtotime($day);
            $model = $model->where('create_time', '>=', $startTime)
                ->where('create_time', '<', $startTime + 86400);
        }
        return $model->where('is_delete', '=', '0')->count();
    }

    /**
     * 获取用户id
     * @return \think\Collection
     */
    public function getUsers($where = null)
    {
        // 获取用户列表
        return $this->where('is_delete', '=', '0')
            ->where($where)
            ->order(['user_id' => 'asc'])
            ->field(['user_id'])
            ->select();
    }

    /**
     * 获取用户列表
     */
    public static function getList($nickName, $grade_id, $reg_date, $params)
    {
        $model = new static();
        //检索：用户名
        if (!empty($nickName)) {
            $model = $model->where('user.nickName|user.mobile|user.user_id', 'like', '%' . $nickName . '%');
        }
        // 检索：会员等级
        if ($grade_id > 0) {
            $model = $model->where('user.grade_id', '=', (int)$grade_id);
        }
        //检索：注册时间
        if (!empty($reg_date[0])) {
            $model = $model->whereTime('user.create_time', 'between', [$reg_date[0], date('Y-m-d 23:59:59', strtotime($reg_date[1]))]);
        }
        //合伙人
        if (isset($params['is_partner']) && $params['is_partner'] != '') {
            $model = $model->where('user.is_partner', '=', (int)$params['is_partner']);
        }
        if (isset($params['is_partner_out']) && $params['is_partner_out'] != '') {
            $model = $model->where('user.is_partner_out', '=', (int)$params['is_partner_out']);
        }
        // 检索：标签
        if (!empty($params['tag_id']) && $params['tag_id'] > 0) {
            $model = $model->where('tag.tag_id', '=', (int)$params['tag_id']);
        }
        if (isset($params['reg_source']) && $params['reg_source']) {
            $model = $model->where('user.reg_source', '=', $params['reg_source']);
        }
        if (self::hasColumn('user', 'reward_freeze') && isset($params['reward_freeze']) && $params['reward_freeze'] !== '') {
            $model = $model->where('user.reward_freeze', '=', (int)$params['reward_freeze']);
        }
        if (self::hasColumn('user', 'job_grade') && !empty($params['job_grade'])) {
            $model = $model->where('user.job_grade', '=', (int)$params['job_grade']);
        }
        // 获取用户列表
        $list = $model->alias('user')->with(['grade', 'referee'])->distinct(true)->field(['user.*'])
            ->where('user.is_delete', '=', '0')
            ->join('user_tag tag', 'user.user_id = tag.user_id', 'left')
            ->order(['user.create_time' => 'desc'])
            ->hidden(['open_id', 'union_id'])
            ->paginate($params)
            ->each(function ($item) {
                if (isset($item['reward_freeze'])) {
                    $item['reward_freeze_text'] = (int)$item['reward_freeze'] === 1 ? '冻结' : '正常';
                }
                if (isset($item['job_grade'])) {
                    $item['job_grade']        = \app\common\service\activity\WzUserIdentityService::normalizeJobGrade((int)$item['job_grade']);
                    $item['job_grade_text']   = \app\common\service\activity\WzUserIdentityService::getJobGradeText($item['job_grade']);
                    $item['agent_level_text'] = \app\common\service\activity\WzUserIdentityService::getAgentLevelText($item);
                }
                if (isset($item['is_store'])) {
                    $item['is_store_text'] = \app\common\service\activity\WzUserIdentityService::getStoreText($item);
                }
                if (isset($item['is_partner'])) {
                    $item['is_partner_text'] = \app\common\service\activity\WzUserIdentityService::getPartnerText($item);
                }
                // 福满堂：会员权益身份 / 消费券全平台互转开关
                if (isset($item['hky_identity'])) {
                    $item['hky_identity_text'] = [0 => '大众', 1 => '白名单', 2 => '黑名单'][(int)$item['hky_identity']] ?? '大众';
                }
                if (isset($item['voucher_free_transfer'])) {
                    $item['voucher_free_transfer_text'] = (int)$item['voucher_free_transfer'] === 1 ? '全平台互转' : '仅同直推线';
                }
                $item['agent_region_text'] = self::buildAgentRegionText($item);

                $item['contribution_remain'] = Db::name('user_energy_record')->whereIn('user_id', $item['user_id'])->sum('remain_energy');
                $item['contribution_total']  = Db::name('user_energy_record')->whereIn('user_id', $item['user_id'])->sum('total_energy');
                $item['contribution_count']  = Db::name('user_energy_record')->whereIn('user_id', $item['user_id'])->count('*');
                return $item;
            });

        //self::appendContributionFields($list);
        return $list;
    }

    /**
     * 用户列表附带贡献值（能量）汇总字段
     * - contribution_remain：未释放贡献值
     * - contribution_total：贡献值总量（含已释放）
     * - contribution_count：贡献值记录条数
     */
    private static function appendContributionFields($list): void
    {
        $items = method_exists($list, 'items') ? $list->items() : [];
        if (empty($items)) {
            return;
        }
        $userIds = [];
        foreach ($items as $item) {
            $uid = (int)(is_array($item) ? ($item['user_id'] ?? 0) : ($item['user_id'] ?? 0));
            if ($uid > 0) {
                $userIds[] = $uid;
            }
        }
        $userIds = array_values(array_unique($userIds));
        $map = [];
        foreach ($userIds as $uid) {
            $map[$uid] = [
                'contribution_remain' => '0.00',
                'contribution_total'  => '0.00',
                'contribution_count'  => 0,
            ];
        }
        if ($userIds === []) {
            return;
        }
        try {
            $prefix = config('database.connections.mysql.prefix');
            $exists = Db::query("SHOW TABLES LIKE '" . addslashes($prefix) . "user_energy_record'");
            if (empty($exists)) {
                foreach ($items as $item) {
                    $item['contribution_remain'] = '0.00';
                    $item['contribution_total']  = '0.00';
                    $item['contribution_count']  = 0;
                }
                return;
            }
            $rows = Db::name('user_energy_record')
                ->whereIn('user_id', $userIds)
                ->field([
                    'user_id',
                    'IFNULL(SUM(remain_energy),0) AS remain_sum',
                    'IFNULL(SUM(total_energy),0) AS total_sum',
                    'COUNT(*) AS record_count',
                ])
                ->group('user_id')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $uid = (int)$row['user_id'];
                $map[$uid] = [
                    'contribution_remain' => number_format((float)$row['remain_sum'], 2, '.', ''),
                    'contribution_total'  => number_format((float)$row['total_sum'], 2, '.', ''),
                    'contribution_count'  => (int)$row['record_count'],
                ];
            }
        } catch (\Throwable $e) {
            // 表异常时保持默认 0，不影响列表
        }
        foreach ($items as $item) {
            $uid = (int)$item['user_id'];
            $stat = $map[$uid] ?? [
                'contribution_remain' => '0.00',
                'contribution_total'  => '0.00',
                'contribution_count'  => 0,
            ];
            $item['contribution_remain'] = $stat['contribution_remain'];
            $item['contribution_total']  = $stat['contribution_total'];
            $item['contribution_count']  = $stat['contribution_count'];
        }
    }

    /**
     * 软删除
     */
    public function setDelete()
    {
        // 判断是否为分销商
        if (AgentUserModel::isAgentUser($this['user_id'])) {
            $this->error = '当前用户为分销商，不可删除';
            return false;
        }
        return $this->transaction(function () {
            // 删除用户推荐关系
            (new AgentUserModel)->onDeleteReferee($this['user_id']);
            // 标记为已删除
            return $this->save(['is_delete' => 1]);
        });
    }

    /**
     * 新增记录
     */
    public function add($data)
    {
        $mobile = $this->where('mobile', '=', $data['mobile'])
            ->where('reg_source', 'in', ['h5', 'app'])
            ->where('is_delete', '=', 0)
            ->count();
        if ($mobile) {
            $this->error = "手机号已存在";
            return false;
        }
        $data['password'] = md5($data['password']);
        $data['grade_id'] = GradeModel::getDefaultGradeId();
        $data['app_id'] = self::$app_id;
        $data['reg_source'] = 'h5';
        return $this->save($data);
    }

    /**
     * 修改记录
     */
    public function edit($data)
    {
        if ($data['mobile']) {
            if ($this['reg_source'] == 'h5' || $this['reg_source'] == 'app') {
                $reg_source = ['h5', 'app'];
            } else {
                $reg_source = [$this['reg_source']];
            }
            $mobile = $this->where('mobile', '=', $data['mobile'])
                ->where('user_id', '<>', $this['user_id'])
                ->where('reg_source', 'in', $reg_source)
                ->where('is_delete', '=', 0)
                ->count();
            if ($mobile) {
                $this->error = "手机号已存在";
                return false;
            }
        }
        if ($data['password']) {
            $data['password'] = md5($data['password']);
        } else {
            unset($data['password']);
        }
        $allowField = ['nickName', 'avatarUrl', 'gender', 'mobile', 'password'];
        if (self::hasPayPasswordColumn()) {
            if ($data['pay_password'] ?? '') {
                $data['pay_password'] = md5($data['pay_password']);
            } else {
                unset($data['pay_password']);
            }
            $allowField[] = 'pay_password';
        } else {
            unset($data['pay_password']);
        }
        return $this->allowField($allowField)->save($data);
    }

    /**
     * 修改用户等级
     * 支持两种等级字段:
     * 1. grade_id - 原会员等级(兼容旧版)
     * 2. job_grade - 消费身份(0-1): 0=游客、1=会员；区域代理请用后台 setAgentRegion
     */
    public function updateGrade($data)
    {
        if (!isset($data['remark'])) {
            $data['remark'] = '';
        }
        
        // 变更前的等级id
        $oldGradeId = $this['grade_id'];
        $oldJobGrade = (int)($this['job_grade'] ?? 0);
        
        return $this->transaction(function () use ($oldGradeId, $oldJobGrade, $data) {
            $updateData = [];
            
            if (isset($data['job_grade'])) {
                $raw = (int)$data['job_grade'];
                if ($raw < 0) {
                    $this->error = 'job_grade 不能小于 0';
                    return false;
                }
                $updateData['job_grade'] = \app\common\service\activity\WzUserIdentityService::normalizeJobGrade($raw);
                
                // 同时记录到日志
                $data['new_grade_id'] = $jobGrade; // 用于日志记录
            }
            
            // 如果传入了 grade_id 字段(兼容旧版)
            if (isset($data['grade_id'])) {
                $updateData['grade_id'] = $data['grade_id'];
                $data['new_grade_id'] = $data['grade_id']; // 用于日志记录
            }
            
            // 如果两个字段都没传,返回错误
            if (empty($updateData)) {
                $this->error = '请传入 grade_id 或 job_grade 字段';
                return false;
            }
            
            // 更新用户的等级
            $status = $this->save($updateData);
            
            // 新增用户等级修改记录
            if ($status) {
                // 根据实际更新的字段记录日志
                $newGradeId = isset($updateData['job_grade']) ? $updateData['job_grade'] : $updateData['grade_id'];
                $oldGradeIdForLog = isset($updateData['job_grade']) ? $oldJobGrade : $oldGradeId;
                
                (new GradeLogModel)->save([
                    'user_id' => $this['user_id'],
                    'old_grade_id' => $oldGradeIdForLog,
                    'new_grade_id' => $newGradeId,
                    'change_type' => ChangeTypeEnum::ADMIN_USER,
                    'remark' => $data['remark'],
                    'app_id' => $this['app_id']
                ]);
            }
            return $status !== false;
        });
    }

    /**
     * 消减用户的实际消费金额
     */
    public function setDecUserExpend($userId, $expendMoney)
    {
        return $this->where(['user_id' => $userId])->dec('expend_money', $expendMoney)->update();
    }

    /**
     * 用户充值
     */
    public function recharge($storeUserName, $source, $data)
    {
        if ($source == 0) {
            return $this->rechargeToBalance($storeUserName, $data['balance']);
        } elseif ($source == 1) {
            return $this->rechargeToPoints($storeUserName, $data['points']);
        } elseif ($source == 3) {
            return $this->rechargeToVoucher($storeUserName, $data['voucher']);
        } elseif ($source == 4) {
            return $this->rechargeToEnergy($storeUserName, $data['energy'] ?? []);
        }
        return false;
    }

    /**
     * 用户充值：余额
     */
    private function rechargeToBalance($storeUserName, $data)
    {
        if (!isset($data['money']) || $data['money'] === '' || $data['money'] < 0) {
            $this->error = '请输入正确的金额';
            return false;
        }
        // 判断充值方式，计算最终金额
        $money = 0;
        if ($data['mode'] === 'inc') {
            $diffMoney = $this['balance'] + $data['money'];
            $money = $data['money'];
        } elseif ($data['mode'] === 'dec') {
            $diffMoney = $this['balance'] - $data['money'] <= 0 ? 0 : $this['balance'] - $data['money'];
            $money = -$data['money'];
        } else {
            $diffMoney = $data['money'];
            $money = $diffMoney - $this['balance'];
        }

        // 验证充值后的余额是否超出范围
        if ($diffMoney > 99999999.99) {
            $this->error = '充值后余额将超出系统限制(99999999.99)';
            return false;
        }

        // 更新记录
        $this->transaction(function () use ($storeUserName, $data, $diffMoney, $money) {
            // 更新账户余额
            $this->where('user_id', '=', $this['user_id'])->update(['balance' => $diffMoney]);
            // 新增余额变动记录
            BalanceLogModel::add(SceneEnum::ADMIN, [
                'user_id' => $this['user_id'],
                'money' => $money,
                'remark' => $data['remark'],
            ], [$storeUserName]);
        });
        return true;
    }

    /**
     * 用户充值：兑换券
     */
    private function rechargeToVoucher($storeUserName, $data)
    {
        if (!isset($data['money']) || $data['money'] === '' || $data['money'] < 0) {
            $this->error = '请输入正确的金额';
            return false;
        }
        // 判断充值方式，计算最终金额
        $money = 0;
        if ($data['mode'] === 'inc') {
            $diffMoney = $this['voucher'] + $data['money'];
            $money = $data['money'];
        } elseif ($data['mode'] === 'dec') {
            $diffMoney = $this['voucher'] - $data['money'] <= 0 ? 0 : $this['voucher'] - $data['money'];
            $money = -$data['money'];
        } else {
            $diffMoney = $data['money'];
            $money = $diffMoney - $this['voucher'];
        }

        // 验证充值后的余额是否超出范围
        if ($diffMoney > 99999999.99) {
            $this->error = '充值后余额将超出系统限制(99999999.99)';
            return false;
        }

        // 更新记录
        $this->transaction(function () use ($storeUserName, $data, $diffMoney, $money) {
            // 更新账户余额
            $this->where('user_id', '=', $this['user_id'])->update(['voucher' => $diffMoney]);
            // 新增余额变动记录
            VoucherLogModel::add([
                'user_id'  => $this['user_id'],
                'scene'    => VoucherLogSceneEnum::ADMIN,
                'value'    => $money,
                'describe' => '平台充值',
                'remark'   => '平台操作',
                'app_id'   => $this['app_id'],
            ]);
        });
        return true;
    }

    /**
     * 用户充值：能量值/贡献值（写入待释放记录，可被直推下级达标下单解锁）
     * params.energy: { mode:inc, money|value:数量, remark }
     */
    private function rechargeToEnergy($storeUserName, $data)
    {
        if (!is_array($data)) {
            $this->error = '参数错误';
            return false;
        }
        $ret = (new EnergyRewardService())->adminRecharge(
            (int)$this['user_id'],
            (int)$this['app_id'],
            $data,
            (string)$storeUserName
        );
        if (empty($ret['ok'])) {
            $this->error = $ret['message'] ?? '充值失败';
            return false;
        }
        return true;
    }

    /**
     * 用户充值：积分
     */
    private function rechargeToPoints($storeUserName, $data)
    {
        if (!isset($data['value']) || $data['value'] === '' || $data['value'] < 0) {
            $this->error = '请输入正确的积分数量';
            return false;
        }
        $points = 0;
        // 判断充值方式，计算最终积分
        if ($data['mode'] === 'inc') {
            $diffMoney = $this['points'] + $data['value'];
            $points = $data['value'];
        } elseif ($data['mode'] === 'dec') {
            $diffMoney = $this['points'] - $data['value'] <= 0 ? 0 : $this['points'] - $data['value'];
            $points = -$data['value'];
        } else {
            $diffMoney = $data['value'];
            $points = $data['value'] - $this['points'];
        }
        // 更新记录
        $this->transaction(function () use ($storeUserName, $data, $diffMoney, $points) {
            $totalPoints = $this['total_points'] + $points <= 0 ? 0 : $this['total_points'] + $points;
            // 更新账户积分
            $this->where('user_id', '=', $this['user_id'])->update([
                'points' => $diffMoney,
                'total_points' => $totalPoints
            ]);
            // 新增积分变动记录
            PointsLogModel::add([
                'user_id' => $this['user_id'],
                'value' => $points,
                'describe' => "后台管理员 [{$storeUserName}] 操作",
                'remark' => $data['remark'],
            ]);
        });
        event('UserGrade', $this['user_id']);
        return true;
    }



    public function updateRewardFreeze($status)
    {
        $service = new ActivityRewardService();
        if (!$service->adminSetRewardFreeze($this['user_id'], $status)) {
            $this->error = '当前数据库未完成活动奖励字段升级';
            return false;
        }
        return true;
    }

    /**
     * 获取用户统计数量
     */
    public function getUserData($startDate, $endDate, $type)
    {
        $model = $this;
        if (!is_null($startDate)) {
            $model = $model->where('create_time', '>=', strtotime($startDate));
        }
        if (is_null($endDate)) {
            $model = $model->where('create_time', '<', strtotime($startDate) + 86400);
        } else {
            $model = $model->where('create_time', '<', strtotime($endDate) + 86400);
        }
        if ($type == 'user_total' || $type == 'user_add') {
            return $model->count();
        } else if ($type == 'user_pay') {
            return $model->where('pay_money', '>', '0')->count();
        } else if ($type == 'user_no_pay') {
            return $model->where('pay_money', '=', '0')->count();
        }
        return 0;
    }

    public function editTag($data)
    {
        // 删除所有标签
        (new UserTagModel())->where('user_id', '=', $this['user_id'])
            ->delete();
        if (isset($data['checkedTag']) && $data['checkedTag']) {
            $tag_list = [];
            foreach ($data['checkedTag'] as $val) {
                $tag_list[] = [
                    'user_id' => $this['user_id'],
                    'tag_id' => $val,
                    'app_id' => self::$app_id
                ];
            }
            return (new UserTagModel())->saveAll($tag_list);
        }
        return true;
    }

    /**
     * 提现驳回：解冻用户余额
     */
    public static function backFreezeMoney($user_id, $money)
    {
        $model = self::detail($user_id);
        return $model->save([
            'balance' => $model['balance'] + $money,
            'freeze_money' => $model['freeze_money'] - $money,
        ]);
    }

    private static function getJobGradeText($jobGrade)
    {
        return \app\common\service\activity\WzUserIdentityService::getJobGradeText((int)$jobGrade);
    }

    private static function buildAgentRegionText($item)
    {
        $provinceId = (int)($item['agent_province_id'] ?? 0);
        $cityId = (int)($item['agent_city_id'] ?? 0);
        $districtId = (int)($item['agent_district_id'] ?? 0);

        if ($provinceId <= 0 && $cityId <= 0 && $districtId <= 0) {
            return '';
        }

        $parts = [];
        if ($provinceId > 0) {
            $name = Db::name('region')->where('id', '=', $provinceId)->value('name');
            if ($name) $parts[] = $name;
        }
        if ($cityId > 0) {
            $name = Db::name('region')->where('id', '=', $cityId)->value('name');
            if ($name) $parts[] = $name;
        }
        if ($districtId > 0) {
            $name = Db::name('region')->where('id', '=', $districtId)->value('name');
            if ($name) $parts[] = $name;
        }
        return implode(' ', $parts);
    }

    /**
     * 现有积分总数
     */
    public function getTotalPoints()
    {
        return $this->where('is_delete', '=', 0)->sum('points');
    }

    /**
     * 积分转余额总数（从余额日志中统计）
     */
    public function getPointsToBalanceTotal()
    {
        return Db::name('user_balance_log')
            ->where('scene', '=', 110)
            ->sum('money');
    }
}
