<?php

namespace app\server\controller;

use app\server\model\chat\ChatUser as ChatUserModel;
use think\facade\Cache;

/**
 * 商户认证
 */
class Passport extends Controller
{
    /**
     * 商户后台登录
     */
    public function login()
    {
        $model = new ChatUserModel();
        if ($data = $model->checkLogin($this->postData())) {
            return $this->renderSuccess('登录成功', compact('data'));
        }
        return $this->renderError($model->getError() ?: '登录失败');
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $token = Request()->header('token');
        Cache::delete('server_token_' . $token);
        return $this->renderSuccess('退出成功');
    }
}
