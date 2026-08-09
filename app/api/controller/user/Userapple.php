<?php

namespace app\api\controller\user;

use app\api\model\user\Userapple as UserappleModel;
use app\api\controller\Controller;
use app\common\model\settings\Setting;

/**
 * 用户管理模型
 */
class Userapple extends Controller
{
    /**
     * 用户自动登录,默认微信小程序
     */
    public function login()
    {
        $model = new UserappleModel;
        $user_id = $model->login($this->request->post());
        return $this->renderSuccess('', [
            'user_id' => $user_id,
            'token' => $model->getToken()
        ]);
    }

    public function policy()
    {
        $service = Setting::getItem('service');
        $privacy = Setting::getItem('privacy');
        return $this->renderSuccess('', [
            'service' => $service['service'],
            'privacy' => $privacy['privacy'],
        ]);
    }
}