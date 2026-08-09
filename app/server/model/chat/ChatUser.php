<?php

namespace app\server\model\chat;

use app\common\library\captcha\Captcode;
use app\common\model\plus\chat\ChatUser as ChatUserModel;
use app\common\model\supplier\Supplier as SupplierModel;
use app\common\model\settings\Setting as SettingModel;
use think\facade\Cache;

/**
 * 客服模型类
 */
class ChatUser extends ChatUserModel
{

    /**
     *检查登录
     */
    public function checkLogin($param)
    {
        $where['user_name'] = $param['username'];
        $where['password'] = salt_hash($param['password']);
        $where['is_delete'] = 0;
        $code_status = SettingModel::getSysConfig()['server_code'];
        //验证验证码
        if ($code_status) {
            $captcode = new Captcode();
            $codeCheck = $captcode->check($param['code'], $param['codeKey'] . '_server_code');
            if ($codeCheck['code'] != 1) {
                $this->error = $codeCheck['msg'];
                return false;
            }
        }
        if (!$user = $this->where($where)->with(['app', 'supplier.logo'])->find()) {
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
        $shopLogo = '';
        if ($user['type'] == 1) {
            if (!$user['supplier']) {
                $this->error = '登录失败, 当前商户不存在';
                return false;
            }
            if ($user['supplier']['is_delete']) {
                $this->error = '登录失败, 当前商户已删除';
                return false;
            }
            if ($user['supplier']['is_recycle']) {
                $this->error = '登录失败, 当前商户已禁止';
                return false;
            }
            $shopLogo = $user['supplier']['logo'] ? $user['supplier']['logo']['file_path'] : '';
            $shopName = $user['supplier']['name'];
        } else {
            $shopName = SettingModel::getItem('store')['name'];
        }
        $socketUrl = SettingModel::getSysConfig()['url'];
        unset($user['app']);
        unset($user['supplier']);
        $token = signToken($user['chat_user_id'], 'server');
        $data = [
            'loginServiceUserVo' => $user,
            'shopLogo' => $shopLogo,
            'shopName' => $shopName,
            'socketUrl' => $socketUrl,
            'token' => $token,
        ];
        Cache::tag('cache')->set('server_token_' . $token, $user['chat_user_id'], 86400 * 30);
        return $data;
    }

    //获取聊天信息
    public function getMessage($data, $user)
    {
        $list = $this->with(['user', 'supplier.logo'])
            ->where('supplier_user_id', '=', $user['supplier_user_id'])
            ->where('user_id', '=', $data['user_id'])
            ->order('chat_id desc')
            ->paginate($data);
        return $list;
    }

    //获取消息条数
    public function mCount($user)
    {
        $num = 0;
        if ($user) {
            $where[] = ['user_id', '=', $user['user_id']];
            $where[] = ['status', '=', 0];
            $where[] = ['msg_type', '=', 1];
            $num = $this->where($where)->count();
        }
        return $num;
    }

    /**
     * 获取用户信息
     */
    public static function getUser($data)
    {
        return (new static())->where(['chat_user_id' => $data['uid']])->with(['app'])->find();
    }
}
