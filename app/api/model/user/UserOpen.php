<?php

namespace app\api\model\user;

use app\api\model\plus\agent\Referee as RefereeModel;
use app\api\model\settings\Setting as SettingModel;
use app\common\model\order\CloudReserveRelation;
use think\facade\Cache;
use app\common\exception\BaseException;
use app\common\model\user\User as UserModel;
use app\common\model\user\Sms as SmsModel;
use app\common\model\user\Grade as GradeModel;

/**
 * 公众号用户模型类
 */
class UserOpen extends UserModel
{
    private $token;

    /**
     * 隐藏字段
     */
    protected $hidden = [
        'open_id',
        'is_delete',
        'app_id',
        'create_time',
        'update_time'
    ];

    /**
     * 用户登录
     */
    public function login($userInfo, $referee_id = 0)
    {
        // 自动注册用户
        $user_id = $this->register($userInfo, $referee_id);
        // 生成token (session3rd)
        $this->token = $this->token($userInfo['openid']);
        // 记录缓存, 7天
        Cache::set($this->token, $user_id, 86400 * 7);
        return $user_id;
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
        return md5($openid . 'token_salt');
    }

    /**
     * 自动注册用户
     */
    private function register($userInfo, $referee_id = 0)
    {
        $data = [];
        //通过unionid查询用户是否存在
        $user = null;
        $data['union_id'] = '';
        if (isset($userInfo['unionid']) && !empty($userInfo['unionid'])) {
            $data['union_id'] = $userInfo['unionid'];
            $user = self::detailByUnionid($userInfo['unionid']);
        }
        // 查询用户是否已存在
        if (!$user) {
            $user = self::detail(['appopen_id' => $userInfo['openid']]);
        }
        if ($user) {
            $model = $user;
            // 只修改union_id
            if (isset($data['union_id'])) {
                $data = [
                    'union_id' => $data['union_id'],
                ];
            } else {
                return $user['user_id'];
            }
        } else {
            $model = $this;
            $data['referee_id'] = $referee_id;
            $data['appopen_id'] = $userInfo['openid'];
            // 用户信息
            $data['nickName'] = preg_replace('/[\xf0-\xf7].{3}/', '', $userInfo['nickname']);
            $data['avatarUrl'] = $userInfo['headimgurl'];
            $data['reg_source'] = 'app';
            //默认等级
            $data['grade_id'] = GradeModel::getDefaultGradeId();
        }

        try {
            $this->startTrans();
            // 保存/更新用户记录
            if (!$model->save(array_merge($data, [
                'app_id' => self::$app_id
            ]))
            ) {
                throw new BaseException(['msg' => '用户注册失败']);
            }
            if (!$user) {
                //注册之后关系绑定
                $this->saveRelation($model, $referee_id);
            }
            $this->commit();
            return $model['user_id'];
        } catch (\Exception $e) {
            $this->rollback();
            throw new BaseException(['msg' => $e->getMessage()]);
        }
    }

    /**
     * 手机号密码用户登录
     */
    public function phoneLogin($data)
    {
        $user = $this->where('mobile', '=', $data['mobile'])
            ->where('password', '=', md5($data['password']))
            ->where('reg_source', 'in', ['h5', 'app'])
            ->order('user_id desc')
            ->find();
        if (!$user) {
            $this->error = '手机号或密码错误';
            return false;
        } else {
            if ($user['is_delete'] == 1) {
                $this->error = '手机号被禁止或删除，请联系客服';
                return false;
            }
            $user_id = $user['user_id'];
            $mobile = $user['mobile'];
        }
        // 生成token (session3rd)
        $this->token = $this->token($mobile);
        // 记录缓存, 30天
        Cache::tag('cache')->set($this->token, $user_id, 86400 * 7);
        return $user_id;
    }

    /**
     * 手机号密码用户登录
     */
    public function smslogin($data)
    {
        $setting = SettingModel::getItem('store');
        if ($setting['h5_sms_open']) {
            if (!$this->check($data)) {
                //return false;
            }
        }
        $user = $this->where('mobile', '=', $data['mobile'])
            ->where('reg_source', 'in', ['h5', 'app'])
            ->where('is_delete', '=', 0)
            ->order('user_id desc')
            ->find();
        if (!$user) {
            // 推荐人可选,不需要强制要求
            try {
                $this->startTrans();
                $data['referee_id'] = isset($data['referee_id']) && $data['referee_id'] ? $data['referee_id'] : 0;
                $data['reg_source'] = isset($data['reg_source']) && $data['reg_source'] ? $data['reg_source'] : 'h5';
                $this->save(array_merge([
                    'mobile' => $data['mobile'],
                    'reg_source' => $data['reg_source'],
                    //默认等级
                    'grade_id' => GradeModel::getDefaultGradeId(),
                    'app_id' => self::$app_id,
                    'password' => md5(substr(md5(time()), 0, 8)),
                    'referee_id' => $data['referee_id']
                ], UserModel::defaultPayPasswordData()));

                //默认昵称
                $this->save(['nickName' => $setting['user_name'] . $this['user_id']]);
                //注册之后关系绑定
                $this->saveRelation($this, $data['referee_id']);
                $this->commit();
                $user_id = $this['user_id'];
                $mobile = $data['mobile'];


            } catch (\Exception $e) {
                $this->rollback();
                throw new BaseException(['msg' => $e->getMessage()]);
            }
        } else {
            $user_id = $user['user_id'];
            $mobile = $user['mobile'];
        }
        // 生成token (session3rd)
        $this->token = $this->token($mobile);
        // 记录缓存, 30天
        Cache::tag('cache')->set($this->token, $user_id, 86400 * 7);
        return $user_id;
    }

    /*
    *重置密码
    */
    public function resetpassword($data)
    {
        if (!$this->check($data)) {
            return false;
        }
        $user = $this->where('mobile', '=', $data['mobile'])
            ->where('reg_source', 'in', ['h5', 'app'])
            ->order('user_id desc')->find();
        if ($user) {
            if ($user['is_delete'] == 1) {
                $this->error = '手机号被禁止或删除，请联系客服';
                return false;
            }
            return $user->save([
                'password' => md5($data['password'])
            ]);
        } else {
            $this->error = '手机号不存在';
            return false;
        }

    }

    /*
    *手机号注册
    */
    public function phoneRegister($data)
    {
        $setting = SettingModel::getItem('store');
        if ($setting['h5_sms_open']) {
            if (!$this->check($data)) {
                return false;
            }
        }
        $user = $this->where('mobile', '=', $data['mobile'])
            ->where('reg_source', 'in', ['h5', 'app'])
            ->where('is_delete', '=', 0)
            ->find();
        if (!$user) {
            try {
                $this->startTrans();
                $data['referee_id'] = isset($data['referee_id']) && $data['referee_id'] ? $data['referee_id'] : 0;
                $data['reg_source'] = isset($data['reg_source']) && $data['reg_source'] ? $data['reg_source'] : 'h5';
                $this->save(array_merge([
                    'mobile' => $data['mobile'],
                    'reg_source' => $data['reg_source'],
                    //默认等级
                    'grade_id' => GradeModel::getDefaultGradeId(),
                    'app_id' => self::$app_id,
                    'password' => md5($data['password']),
                    'referee_id' => $data['referee_id']
                ], UserModel::defaultPayPasswordData()));
                //默认昵称
                $this->save(['nickName' => $setting['user_name'] . $this['user_id']]);
                //注册之后关系绑定
                $this->saveRelation($this, $data['referee_id']);
                $this->commit();
                // 注册成功后自动登录，与 phonelogin 保持一致
                return $this->phoneLogin([
                    'mobile' => $data['mobile'],
                    'password' => $data['password'],
                ]);
            } catch (\Exception $e) {
                $this->rollback();
                throw new BaseException(['msg' => $e->getMessage()]);
            }
        } else {
            $this->error = '手机号已存在';
            return false;
        }

    }

    /**
     *修改密码
     */
    public function changePassword($data, $user)
    {
        $setting = SettingModel::getItem('store');
        if ($setting['h5_sms_open']) {
            $data['mobile'] = $user['mobile'];
            // if (!$this->check($data)) {
            //     return false;
            // }
        }
        return $user->save([
            'password' => md5($data['password'])
        ]);
    }

    /**
     *修改手机号
     */
    public function changeMobile($data, $user)
    {
        if ($user['mobile'] == $data['mobile']) {
            $this->error = '新手机号不能和原手机号一样';
            return false;
        }
        if ($user['reg_source'] == 'h5' || $user['reg_source'] == 'app') {
            $reg_source = ['h5', 'app'];
        } else {
            $reg_source = [$user['reg_source']];
        }
        //判断新手机号是否存在
        $isExist = $this->where('mobile', '=', $data['mobile'])
            ->where('reg_source', 'in', $reg_source)
            ->where('is_delete', '=', 0)
            ->find();
        if ($isExist) {
            $this->error = '新手机号已存在';
            return false;
        }
        $setting = SettingModel::getItem('store');
        if ($setting['h5_sms_open']) {
            if (!$this->check($data)) {
                return false;
            }
        }
        return $user->save([
            'mobile' => $data['mobile']
        ]);
    }

    /**
     * 验证
     */
    private function check($data)
    {
        //判断验证码是否过期、是否正确
        $sms_model = new SmsModel();
        $sms_record_list = $sms_model
            ->where('mobile', '=', $data['mobile'])
            ->order(['create_time' => 'desc'])
            ->limit(1)->select();

        if (count($sms_record_list) == 0) {
            $this->error = '未查到短信发送记录';
            return false;
        }
        $sms_model = $sms_record_list[0];
        if ((time() - strtotime($sms_model['create_time'])) / 60 > 30) {
            $this->error = '短信验证码超时';
            return false;
        }
        if ($sms_model['code'] != $data['code']) {
            $this->error = '验证码不正确';
            return false;
        }
        return true;
    }
}
