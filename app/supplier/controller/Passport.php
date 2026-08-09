<?php

namespace app\supplier\controller;

use app\supplier\model\supplier\User;
use app\supplier\model\settings\Setting as SettingModel;
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
        $data = $this->postData();
        $data['password'] = salt_hash($data['password']);
        $model = new User();
        if ($userInfo = $model->checkLogin($data)) {
            $url = SettingModel::getSysConfig()['url'];
            //当前系统版本
            $version = get_version();
            return $this->renderSuccess('登录成功', [
                'url' => $url,
                'user_name' => $userInfo['user_name'],
                'token' => $userInfo['token'],
                'app_id' => $userInfo['app']['app_id'],
                'shop_name' => $userInfo['supplier_name'],
                'version' => $version,
                'supplier_user_id' => $userInfo['supplier_user_id'],
                'shop_supplier_id' => $userInfo['shop_supplier_id'],
                'user_id' => $userInfo['user_id'],
                'logoUrl' => $userInfo['logoUrl']
            ]);
        }
        return $this->renderError($model->getError() ?: '登录失败');
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $token = Request()->header('token');
        Cache::delete('supplier_token_' . $token);
        return $this->renderSuccess('退出成功');
    }

    /*
   * 修改密码
   */
    public function editPass()
    {
        $model = new User();
        if ($model->editPass($this->postData(), $this->supplier['user'])) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }
}
