<?php

namespace app\supplier\model\supplier;

use app\common\library\captcha\Captcode;
use app\common\model\supplier\LoginLog as LoginLogModel;
use app\common\model\supplier\User as UserModel;
use app\supplier\model\settings\Setting as SettingModel;
use app\supplier\model\supplier\Supplier as SupplierModel;
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
        $code_status = SettingModel::getSysConfig()['supplier_code'];
        //验证验证码
        if ($code_status) {
            $captcode = new Captcode();
            $codeCheck = $captcode->check($data['code'], $data['codeKey'] . '_supplier_code');
            if ($codeCheck['code'] != 1) {
                $this->error = $codeCheck['msg'];
                return false;
            }
        }
        if (!$user = $this->where($where)->with(['app'])->order('supplier_user_id desc')->find()) {
            return false;
        }
        if (empty($user['app'])) {
            $this->error = '登录失败, 未找到应用信息';
            return false;
        }
        if ($user['app']['is_delete']) {
            $this->error = '登录失败, 当前用户已删除';
            return false;
        }
        $supplier = SupplierModel::detail($user['shop_supplier_id']);
        if (!$supplier) {
            $this->error = '登录失败, 当前商户不存在';
            return false;
        }
        if ($supplier['is_delete']) {
            $this->error = '登录失败, 当前商户已删除';
            return false;
        }
        if ($supplier['is_recycle']) {
            $this->error = '登录失败, 当前商户已禁止';
            return false;
        }
        // 商城名称
        $setting = SettingModel::getItem('store', $user['app']['app_id']);
        $user['supplier_name'] = $supplier['name'];
        $user['logoUrl'] = $supplier['logo'] ? $supplier['logo']['file_path'] : $setting['logoUrl'];
        // 保存登录状态
        $user['token'] = signToken($user['supplier_user_id'], 'supplier');
        Cache::tag('cache')->set('supplier_token_' . $user['token'], $user['supplier_user_id'], 86400 * 30);
        // 写入登录日志
        LoginLogModel::add($user, \request()->ip(), '登录成功', $user['app']['app_id']);
        return $user;
    }


    /*
    * 修改密码
    */
    public function editPass($data, $user)
    {
        $user_info = User::detail($user['supplier_user_id']);
        if ($data['password'] != $data['confirmPass']) {
            $this->error = '新密码输入不一致';
            return false;
        }
        if ($user_info['password'] != salt_hash($data['oldpass'])) {
            $this->error = '原始密码错误';
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
        return (new static())->where(['supplier_user_id' => $data['uid']])->with(['app'])->find();
    }

}