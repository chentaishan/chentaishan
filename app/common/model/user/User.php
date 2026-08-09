<?php


namespace app\common\model\user;

use app\common\model\BaseModel;
use app\common\model\settings\Setting as SettingModel;
use app\common\model\user\PointsLog as PointsLogModel;
use app\common\model\supplier\User as SupplierUserModel;
use app\common\model\plus\agent\Referee as RefereeModel;
use think\facade\Db;

/**
 * 用户模型
 */
class User extends BaseModel
{
    /** 默认支付密码（明文，入库为 MD5） */
    public const DEFAULT_PAY_PASSWORD = '654321';

    protected $pk = 'user_id';
    protected $name = 'user';

    /** @var array<string, bool> */
    private static $columnCache = [];

    /**
     * 追加字段类型转换
     */
    protected $type = [
        'team_total_performance'  => 'float',
        'direct_push_count'       => 'integer',
    ];

    /**
     * 默认头像
     */
    public function getAvatarUrlAttr($value)
    {
        return $value ? $value : SettingModel::getItem('store', self::$app_id)['avatarUrl'];
    }

    /**
     * 关联会员等级表
     */
    public function grade()
    {
        return $this->belongsTo('app\\common\\model\\user\\Grade', 'grade_id', 'grade_id');
    }

    /**
     * 关联收货地址表
     */
    public function address()
    {
        return $this->hasMany('app\\common\\model\\user\\UserAddress', 'address_id', 'address_id');
    }

    /**
     * 关联供应商表
     */
    public function supplierUser()
    {
        return $this->hasOne('app\\common\\model\\supplier\\User', 'user_id', 'user_id')->where('is_delete', '=', 0);
    }

    /**
     * 关联收货地址表 (默认地址)
     */
    public function addressDefault()
    {
        return $this->belongsTo('app\\common\\model\\user\\UserAddress', 'address_id', 'address_id');
    }

    /**
     * 关联推荐人
     */
    public function referee()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'referee_id', 'user_id')->where('is_delete', '=', 0)->field(['user_id', 'nickName']);
    }

    /**
     * 获取用户信息
     */
    public static function detail($where)
    {
        $model = new static;
        $filter = ['is_delete' => 0];
        if (is_array($where)) {
            $filter = array_merge($filter, $where);
        } else {
            $filter['user_id'] = (int)$where;
        }
        return $model->where($filter)->with(['address', 'addressDefault', 'grade'])->find();
    }
    /**
     * 通过手机号获取个人信息
     */
    public static function detailByPhone($phone)
    {
        $model = new static;
        $filter = ['is_delete' => 0];
        $filter = array_merge($filter, ['mobile' => $phone]);
        return $model->where($filter)->with(['address', 'addressDefault', 'grade'])->find();
    }

    /**
     * 获取用户信息
     */
    public static function detailByUnionid($unionid)
    {
        $model = new static;
        $filter = ['is_delete' => 0];
        $filter = array_merge($filter, ['union_id' => $unionid]);
        return $model->where($filter)->with(['address', 'addressDefault', 'grade'])->find();
    }

    /**
     * 指定会员等级下是否存在用户
     */
    public static function checkExistByGradeId($gradeId)
    {
        $model = new static;
        return !!$model->where('grade_id', '=', (int)$gradeId)
            ->where('is_delete', '=', 0)
            ->value('user_id');
    }

    /**
     * 累积用户总消费金额
     */
    public function setIncPayMoney($money)
    {
        return $this->where('user_id', '=', $this['user_id'])->inc('pay_money', $money)->update();
    }

    /**
     * 累积用户实际消费的金额 (批量)
     */
    public function onBatchIncExpendMoney($data)
    {
        foreach ($data as $userId => $expendMoney) {
            $this->where(['user_id' => $userId])->inc('expend_money', $expendMoney)->update();
            event('UserGrade', $userId);
        }
        return true;
    }

    /**
     * 累积用户的可用积分数量 (批量)
     */
    public function onBatchIncPoints($data)
    {
        foreach ($data as $userId => $expendPoints) {
            $this->where(['user_id' => $userId])
                ->inc('points', $expendPoints)
                ->inc('total_points', $expendPoints)
                ->update();
            event('UserGrade', $this['user_id']);
        }
        return true;
    }

    /**
     * 累积用户的可用积分
     */
    public function setIncPoints($points, $describe, $upgrade = true)
    {
        // 新增积分变动明细
        PointsLogModel::add([
            'user_id' => $this['user_id'],
            'value' => $points,
            'describe' => $describe,
            'app_id' => $this['app_id'],
        ]);

        // 更新用户可用积分
        $data['points'] = ($this['points'] + $points) <= 0 ? 0 : $this['points'] + $points;
        // 用户总积分
        if ($points > 0) {
            $data['total_points'] = $this['total_points'] + $points;
        }
        $this->where('user_id', '=', $this['user_id'])->update($data);
        if ($upgrade) {
            event('UserGrade', $this['user_id']);
        }
        return true;
    }

    //更新用户类型
    public static function updateType($user_id, $user_type)
    {
        $model = new static;
        return $model->where('user_id', '=', $user_id)->update([
            'user_type' => $user_type
        ]);
    }

    /**
     * 用户是否成功成为供应商，如果不是则为审核中
     * 申请中的不算
     */
    public static function isSupplier($user_id)
    {
        return SupplierUserModel::detail([
                'user_id' => $user_id
            ]) != null;
    }

    /**
     * 累计邀请书
     */
    public function setIncInvite($user_id)
    {
        $this->where('user_id', '=', $user_id)->inc('total_invite')->update();
        event('UserGrade', $user_id);
    }

    /**
     * 注册之后关系绑定
     */
    public function saveRelation($user, $refereeId)
    {
        if ($refereeId > 0) {
            // 记录推荐人关系
            RefereeModel::createRelation($user['user_id'], $refereeId);
            //更新用户邀请数量
            $this->setIncInvite($refereeId);
        }
    }

    /**
     * 提现打款成功：累积提现余额
     */
    public static function totalMoney($user_id, $money)
    {
        $model = self::detail($user_id);
        return $model->save([
            'freeze_money' => $model['freeze_money'] - $money,
            'cash_money' => $model['cash_money'] + $money,
        ]);
    }

    public static function defaultPayPasswordHash(): string
    {
        return md5(self::DEFAULT_PAY_PASSWORD);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $prefix = (string)(config('database.connections.mysql.prefix') ?? '');
            $columns = array_column(
                Db::query('SHOW COLUMNS FROM `' . $prefix . $table . '`'),
                'Field'
            );
            self::$columnCache[$key] = in_array($column, $columns, true);
        } catch (\Throwable $e) {
            self::$columnCache[$key] = false;
        }
        return self::$columnCache[$key];
    }

    public static function hasPayPasswordColumn(): bool
    {
        return self::hasColumn('user', 'pay_password');
    }

    /**
     * 校验支付密码，失败时返回错误提示
     */
    public static function getPayPasswordVerifyError($user, string $password): ?string
    {
        if (!self::hasPayPasswordColumn()) {
            return '支付密码功能未启用，请先联系管理员升级数据库';
        }
        $password = trim($password);
        if ($password === '') {
            return '请输入支付密码';
        }
        $stored = trim((string)($user['pay_password'] ?? ''));
        if ($stored === '') {
            $stored = self::defaultPayPasswordHash();
        }
        if (md5($password) !== $stored) {
            return '支付密码错误';
        }
        return null;
    }

    /**
     * 校验支付密码
     */
    public static function verifyPayPassword($user, string $password): bool
    {
        return self::getPayPasswordVerifyError($user, $password) === null;
    }

    /**
     * 新用户默认支付密码字段
     */
    public static function defaultPayPasswordData(): array
    {
        if (!self::hasPayPasswordColumn()) {
            return [];
        }
        return ['pay_password' => self::defaultPayPasswordHash()];
    }

    /**
     * 是否同一条推荐关系线（一方为另一方的上级或下级）
     */
    public static function isSameReferralLine(int $userIdA, int $userIdB): bool
    {
        if ($userIdA <= 0 || $userIdB <= 0 || $userIdA === $userIdB) {
            return false;
        }

        $userA = self::where('user_id', '=', $userIdA)->where('is_delete', '=', 0)->find();
        $userB = self::where('user_id', '=', $userIdB)->where('is_delete', '=', 0)->find();
        if (!$userA || !$userB) {
            return false;
        }

        if ((int)$userA['referee_id'] === $userIdB || (int)$userB['referee_id'] === $userIdA) {
            return true;
        }

        return self::isReferralAncestor($userIdA, $userIdB) || self::isReferralAncestor($userIdB, $userIdA);
    }

    private static function isReferralAncestor(int $ancestorId, int $descendantId): bool
    {
        $currentId = $descendantId;
        $depth = 0;
        while ($currentId > 0 && $depth < 220800) {
            $refereeId = (int)Db::name('user')->where('user_id', '=', $currentId)->value('referee_id');
            if ($refereeId <= 0) {
                return false;
            }
            if ($refereeId === $ancestorId) {
                return true;
            }
            $currentId = $refereeId;
            $depth++;
        }
        return false;
    }
}