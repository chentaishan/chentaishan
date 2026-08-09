<?php

namespace app\server\controller;

use app\common\model\settings\Setting as SettingModel;

/**
 * 后台首页控制器
 */
class Index extends Controller
{
    /**
     * 登录数据
     */
    public function base()
    {
        $config = SettingModel::getSysConfig();
        $server_name = $config['server_name'];
        $server_bg_img = $config['server_bg_img'];
        $codeData = (new SettingModel())->getLoginCode('server_code');
        return $this->renderSuccess('', compact('server_name', 'server_bg_img', 'codeData'));
    }
}