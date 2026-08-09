<?php

namespace app\shop\model\shop;

use app\common\library\captcha\Captcode;
use app\common\model\shop\LoginLog as LoginLogModel;
use app\common\model\shop\User as UserModel;
use app\common\model\settings\Setting as SettingModel;
use think\facade\Cache;

/**
 * 后台管理员登录模型
 */
class User extends UserModel
{
    /**
     *检查登录
     */
    public function checkLogin($data)
    {
        $where['user_name'] = $data['username'];
        $where['password'] = $data['password'];
        $where['is_delete'] = 0;
        $code_status = SettingModel::getSysConfig()['shop_code'];
        //验证验证码
        if ($code_status) {
            $captcode = new Captcode();
            $codeCheck = $captcode->check($data['code'], $data['codeKey'] . '_shop_code');
            if ($codeCheck['code'] != 1) {
                $this->error = $codeCheck['msg'];
                return false;
            }
        }
        if (!$user = $this->where($where)->with(['app'])->find()) {
            return false;
        }
        if (empty($user['app'])) {
            $this->error = '登录失败, 未找到应用信息';
            return false;
        }
        if ($user['app']['is_recycle']) {
            $this->error = '登录失败, 当前应用已禁用';
            return false;
        }
        if ($user['app']['is_delete']) {
            $this->error = '登录失败, 当前应用已删除';
            return false;
        }
        // 保存登录状态
        $user['token'] = signToken($user['shop_user_id'], 'shop');
        Cache::tag('cache')->set('shop_token_' . $user['token'], $user['shop_user_id'], 86400 * 30);
        // 写入登录日志
        LoginLogModel::add($where['user_name'], \request()->ip(), '登录成功', $user['app']['app_id']);
        return $user;
    }


    /*
    * 修改密码
    */
    public function editPass($data, $user)
    {
        $user_info = User::detail($user['shop_user_id']);
        if ($user_info['password'] != salt_hash($data['oldpass'])) {
            $this->error = '旧密码错误';
            return false;
        }
        if ($data['password'] != $data['confirmPass']) {
            $this->error = '两次密码不相同';
            return false;
        }
        $date['password'] = salt_hash($data['password']);
        $user_info->save($date);
        return true;
    }

    /**
     * 获取用户信息
     */
    public static function getUser($data)
    {
        return (new static())->where(['shop_user_id' => $data['uid']])->with(['app'])->find();
    }

}