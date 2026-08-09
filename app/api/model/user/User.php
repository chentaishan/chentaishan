<?php

namespace app\api\model\user;

use app\api\model\plus\coupon\UserCoupon;
use app\common\library\helper;
use app\common\model\page\CenterMenu as CenterMenuModel;
use think\facade\Cache;
use app\common\exception\BaseException;
use app\common\model\user\User as UserModel;
use app\api\model\plus\agent\Referee as RefereeModel;
use app\common\library\easywechat\AppWx;
use app\common\model\user\Grade as GradeModel;
use app\common\library\wechat\WxBizDataCrypt;
use app\common\model\settings\Setting as SettingModel;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use think\facade\Db;

/**
 * 用户模型类
 */
class User extends UserModel
{
    private $token;

    /**
     * 隐藏字段
     */
    protected $hidden = [
        'is_delete',
        'create_time',
        'update_time',
        'pay_password',
    ];

    /**
     * 获取用户信息
     */
    public static function getUser($token)
    {
        $userId = Cache::get($token);
        return (new static())->where(['user_id' => $userId])->with(['address', 'addressDefault', 'grade', 'supplierUser'])->find();
    }

    /**
     * 用户登录
     */
    public function login($post)
    {
        // 微信登录 获取session_key
        $app = AppWx::getApp();
        $utils = $app->getUtils();
        $session = $utils->codeToSession($post['code']);
        // 自动注册用户
        $refereeId = isset($post['referee_id']) && $post['referee_id'] ? $post['referee_id'] : 0;
        $userInfo = $this->register($session, $refereeId);
        return $userInfo;
    }

    /**
     * 用户登录
     */
    public function userLogin($code)
    {
        // 微信登录 获取session_key
        $app = AppWx::getApp();
        $utils = $app->getUtils();
        $session = $utils->codeToSession($code);
        $userInfo = "";
        if (isset($session['unionid']) && !empty($session['unionid'])) {
            $userInfo = self::detailByUnionid($session['unionid']);
        }
        if (!$userInfo) {
            $userInfo = $this->where('open_id', '=', $session['openid'])
                ->where('is_delete', '=', 0)
                ->find();
        }
        if (!$userInfo) {
            $this->error = '用户不存在，请重新登录';
            return false;
        }
        $this->token = $this->token($session['openid']);
        // 记录缓存, 7天
        Cache::tag('cache')->set($this->token, $userInfo['user_id'], 86400 * 7);
        return $userInfo['user_id'];
    }

    /**
     * 用户登录
     */
    public function bindMobile($post)
    {
        try {
            $user_id = $post['user_id'];
            $user = self::detail($user_id);
            if (!$user) {
                $this->error = '授权失败，请重新授权';
                return false;
            }
            if ($user['mobile']) {
                // 生成token (session3rd)
                $this->token = $this->token($user_id);
                // 记录缓存, 7天
                Cache::tag('cache')->set($this->token, $user_id, 86400 * 7);
                return $user_id;
            }
            // 微信登录 获取session_key
            $app = AppWx::getApp();
            $session = AppWx::sessionKey($app, $post['code']);
            if (!$session) {
                $this->error = '授权失败，请重新授权';
                return false;
            }
            $iv = $post['iv'];
            $encrypted_data = $post['encrypted_data'];
            $utils = $app->getUtils();
            $result = $utils->decryptSession($session['session_key'], $iv, $encrypted_data);
            if (isset($result['phoneNumber']) && $result['phoneNumber']) {
                $this->startTrans();
                $this->where('user_id', '=', $user_id)
                    ->update([
                        'mobile' => $result['phoneNumber'],
                    ]);
                // 生成token (session3rd)
                $this->token = $this->token($user_id);
                // 记录缓存, 7天
                Cache::tag('cache')->set($this->token, $user_id, 86400 * 7);
                $this->commit();
                return $user_id;
            } else {
                $this->error = '登录失败';
                return false;
            }
        } catch (\Exception $e) {
            $this->rollback();
            $this->error = '获取手机号失败，请重试';
            return false;
        }
    }

    /**
     * 获取token
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * 生成用户认证的token
     */
    private function token($openid)
    {
        $app_id = self::$app_id;
        // 生成一个不会重复的随机字符串
        $guid = \getGuidV4();
        // 当前时间戳 (精确到毫秒)
        $timeStamp = microtime(true);
        // 自定义一个盐
        $salt = 'token_salt';
        return md5("{$app_id}_{$timeStamp}_{$openid}_{$guid}_{$salt}");
    }

    /**
     * 自动注册用户
     */
    private function register($decryptedData, $refereeId)
    {
        //通过unionid查询用户是否存在
        $user = null;
        $data['union_id'] = '';
        if (isset($decryptedData['unionid']) && !empty($decryptedData['unionid'])) {
            $data['union_id'] = $decryptedData['unionid'];
            $user = self::detailByUnionid($decryptedData['unionid']);
        }
        if (!$user) {
            // 通过open_id查询用户是否已存在
            $user = self::detail(['open_id' => $decryptedData['openid']]);
        }
        if ($user) {
            $model = $user;
            // 只修改union_id
            $data = [
                'union_id' => $data['union_id'],
            ];
        } else {
            $model = $this;
            $data['referee_id'] = $refereeId;
            $data['reg_source'] = 'wx';
            //默认等级
            $data['grade_id'] = GradeModel::getDefaultGradeId();
        }
        $this->startTrans();
        try {
            // 保存/更新用户记录
            $saveData = array_merge($data, [
                'open_id' => $decryptedData['openid'],
                'app_id' => self::$app_id
            ]);
            if (!$user) {
                $saveData = array_merge($saveData, UserModel::defaultPayPasswordData());
            }
            if (!$model->save($saveData)) {
                throw new BaseException(['msg' => '用户注册失败']);
            }
            if (!$user) {
                $setting = SettingModel::getItem('store');
                //默认昵称
                $model->save(['nickName' => $setting['user_name'] . $model['user_id']]);
                //注册之后关系绑定
                $this->saveRelation($model, $refereeId);
            }
            $this->commit();
            return $model;
        } catch (\Exception $e) {
            $this->rollback();
            throw new BaseException(['msg' => $e->getMessage()]);
        }
    }

    /**
     *统计被邀请人数
     */
    public function getCountInv($user_id)
    {
        return $this->where('referee_id', '=', $user_id)->count('user_id');
    }

    /**
     * 签到更新用户积分
     */
    public function setSignAward($user_id, $days, $sign_conf, $sign_date)
    {
        $rank = $sign_conf['ever_sign'];
        $coupon = [];
        $coupon_num = 0;
        if ($sign_conf['is_coupon'] && count($sign_conf['coupon']) > 0) {
            $coupon = $sign_conf['coupon'];
        }
        if ($sign_conf['is_increase'] == 'true') {
            if ($days >= $sign_conf['no_increase']) {
                $days = $sign_conf['no_increase'] - 1;
            }
            $rank = ($days - 1) * $sign_conf['increase_reward'] + $rank;
        }
        //是否奖励
        if (isset($sign_conf['reward_data'])) {
            $arr = array_column($sign_conf['reward_data'], 'day');
            if (in_array($days, $arr)) {
                $key = array_search($days, $arr);
                if ($sign_conf['reward_data'][$key]['is_point'] == 'true') {
                    $rank = $sign_conf['reward_data'][$key]['point'] + $rank;
                }
                if ($sign_conf['reward_data'][$key]['is_coupon'] == 'true' && count($sign_conf['reward_data'][$key]['coupon']) > 0) {
                    $coupon = array_merge($coupon, $sign_conf['reward_data'][$key]['coupon']);
                }
            }
        }
        //赠送优惠券
        if ($coupon) {
            $coupon_num = helper::getArrayColumnSum($coupon, 'coupon_num');
            $UserCouponModel = new UserCoupon;
            $UserCouponModel->addNewUserCoupon(json_encode($coupon), $user_id);
        }
        // 新增积分变动明细
        $this->setIncPoints($rank, '用户签到：签到日期' . $sign_date);
        $result = [
            'points' => $rank,
            'coupon' => $coupon,
            'coupon_num' => $coupon_num
        ];
        return $result;
    }

    /**
     * 个人中心菜单列表
     */
    public static function getMenus($user, $source)
    {
        // 系统菜单
        $sys_menus = CenterMenuModel::getSysMenu();
        // 查询用户菜单
        $model = new CenterMenuModel();
        $user_menus = $model->getAll();
        $user_menu_tags = [];
        foreach ($user_menus as $menu) {
            $menu['sys_tag'] != '' && array_push($user_menu_tags, $menu['sys_tag']);
        }
        $save_data = [];
        foreach ($sys_menus as $menu) {
            if ($menu['sys_tag'] != '' && !in_array($menu['sys_tag'], $user_menu_tags)) {
                $save_data[] = array_merge($sys_menus[$menu['sys_tag']], [
                    'sort' => 100,
                    'app_id' => self::$app_id
                ]);
            }
        }
        if (count($save_data) > 0) {
            $model->saveAll($save_data);
            Cache::delete('center_menu_' . self::$app_id);
            $user_menus = $model->getAll();
        }
        $menus_arr = [];
        foreach ($user_menus as $menu) {
            if ($menu['status'] == 1) {
                array_push($menus_arr, $menu);
            }
        }
        $sign_conf = SettingModel::getItem('sign');
        foreach ($menus_arr as $key => $menus) {
            if ($menus['sys_tag'] == "signin" && !$sign_conf['is_open']) {
                unset($menus_arr[$key]);
            }
            if (strpos($menus['image_url'], 'http') !== 0) {
                $menus['image_url'] = self::$base_url . $menus['image_url'];
            }
        }
        return $menus_arr;
    }

    /**
     * 修改会员信息
     */
    public function edit($data)
    {
        $this->startTrans();
        try {
            //完成成长任务
            if ($this['nickName'] != $data['nickName']) {
                $data['task_type'] = "base";
                $data['user_id'] = $this['user_id'];
                // event('UserTask', $data);
            } elseif ($this['avatarUrl'] != $data['avatarUrl']) {
                $data['task_type'] = "image";
                $data['user_id'] = $this['user_id'];
                // event('UserTask', $data);
            }
            unset($data['points']);
            $this->allowField(['avatarUrl', 'nickName', 'gender'])->save($data);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 积分转换余额
     */
    public function transPoints($points)
    {
        $setting = SettingModel::getItem('points');
        $ratio = $setting['discount']['discount_ratio'];
        if (!$setting['is_trans_balance']) {
            $this->error = "暂未开启积分转换余额";
            return false;
        }
        if ($points <= 0) {
            $this->error = "转换积分不能小于0";
            return false;
        }
        if ($this['points'] < $points) {
            $this->error = "不能大于当前积分";
            return false;
        }
        $this->startTrans();
        try {
            $balance = round($ratio * $points, 2);
            //添加积分记录
            $describe = "积分转换余额";
            $this->setIncPoints(-$points, $describe);
            //添加余额记录
            $balance > 0 && BalanceLogModel::add(BalanceLogSceneEnum::POINTS, [
                'user_id' => $this['user_id'],
                'money' => $balance,
                'app_id' => self::$app_id
            ], '');
            $this->save(['points' => $this['points'] - $points, 'balance' => $this['balance'] + $balance]);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }




    public function transferBalance($data)
    {
        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            $this->error = '转账金额必须大于0';
            return false;
        }

        $targetUserId = (int)($data['target_user_id'] ?? 0);
        $mobile = trim((string)($data['mobile'] ?? ''));
        if ($targetUserId <= 0 && $mobile === '') {
            $this->error = '请选择收款用户';
            return false;
        }

        $payPassword = trim((string)($data['pay_password'] ?? ''));
        $payPasswordError = UserModel::getPayPasswordVerifyError($this, $payPassword);
        if ($payPasswordError !== null) {
            $this->error = $payPasswordError;
            return false;
        }

        $this->startTrans();
        try {
            $sender = self::where('user_id', '=', $this['user_id'])->where('is_delete', '=', 0)->lock(true)->find();
            if (!$sender) {
                throw new \Exception('转出用户不存在');
            }

            $targetQuery = self::where('is_delete', '=', 0);
            if ($targetUserId > 0) {
                $targetQuery->where('user_id', '=', $targetUserId);
            } else {
                $targetQuery->where('mobile', '=', $mobile);
            }
            $receiver = $targetQuery->lock(true)->find();
            if (!$receiver) {
                throw new \Exception('收款用户不存在');
            }

            if ((int)$receiver['user_id'] === (int)$sender['user_id']) {
                throw new \Exception('不能给自己转账');
            }

            if (!UserModel::isSameReferralLine((int)$sender['user_id'], (int)$receiver['user_id'])) {
                throw new \Exception('非同一关系线用户不能互转');
            }

            if ((float)$sender['balance'] < $amount) {
                throw new \Exception('余额不足');
            }

            self::where('user_id', '=', $sender['user_id'])->update([
                'balance' => Db::raw('balance-' . $amount),
            ]);
            self::where('user_id', '=', $receiver['user_id'])->update([
                'balance' => Db::raw('balance+' . $amount),
            ]);

            BalanceLogModel::add(BalanceLogSceneEnum::TRANSFER, [
                'user_id' => $sender['user_id'],
                'money' => -$amount,
                'app_id' => self::$app_id,
            ], ['转出给用户' . $receiver['user_id']]);

            BalanceLogModel::add(BalanceLogSceneEnum::TRANSFER, [
                'user_id' => $receiver['user_id'],
                'money' => $amount,
                'app_id' => self::$app_id,
            ], ['收到用户' . $sender['user_id'] . '转账']);

            $this->commit();
            return [
                'target_user_id' => (int)$receiver['user_id'],
                'target_mobile' => (string)$receiver['mobile'],
                'target_nickName' => (string)$receiver['nickName'],
                'amount' => $amount,
                'balance' => round((float)$sender['balance'] - $amount, 2),
            ];
        } catch (\Exception $e) {
            $this->rollback();
            $this->error = $e->getMessage();
            return false;
        }
    }
    /**
     * 修改支付密码
     */
    public function changePayPassword(array $data): bool
    {
        if (!UserModel::hasPayPasswordColumn()) {
            $this->error = '支付密码功能未启用，请先联系管理员升级数据库';
            return false;
        }

        $oldPassword = trim((string)($data['old_pay_password'] ?? ''));
        $newPassword = trim((string)($data['new_pay_password'] ?? ''));
        $confirmPassword = trim((string)($data['confirm_pay_password'] ?? ''));

        if ($oldPassword === '') {
            $this->error = '请输入原支付密码';
            return false;
        }
        $oldError = UserModel::getPayPasswordVerifyError($this, $oldPassword);
        if ($oldError !== null) {
            $this->error = $oldError === '支付密码错误' ? '原支付密码错误' : $oldError;
            return false;
        }
        if ($newPassword === '') {
            $this->error = '请输入新支付密码';
            return false;
        }
        if (!preg_match('/^\d{6}$/', $newPassword)) {
            $this->error = '新支付密码须为6位数字';
            return false;
        }
        if ($newPassword === $oldPassword) {
            $this->error = '新支付密码不能与原支付密码相同';
            return false;
        }
        if ($confirmPassword === '') {
            $this->error = '请确认新支付密码';
            return false;
        }
        if ($newPassword !== $confirmPassword) {
            $this->error = '两次输入的新支付密码不一致';
            return false;
        }

        return (bool)$this->save(['pay_password' => md5($newPassword)]);
    }

    public function freezeMoney($money)
    {
        return $this->save([
            'balance' => $this['balance'] - $money,
            'freeze_money' => $this['freeze_money'] + $money,
        ]);
    }

    public function setDelete($user)
    {
        return $user->save([
            'is_delete' => 1
        ]);
    }

    /**
     * 退出登录
     */
    public function logOut($token)
    {
        Cache::delete($token);
        return true;
    }
}
