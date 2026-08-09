<?php

namespace app\admin\controller;

use app\common\model\settings\Setting as SettingModel;

/**
 * 后台首页
 */
class Index extends Controller
{
    /**
     * 后台首页
     */
    public function index()
    {
        $version = get_version();
        return $this->renderSuccess('', compact('version'));
    }

    /**
     * 登录数据
     */
    public function base()
    {
        $config = SettingModel::getSysConfig();
        $settings = [
            'admin_name' => $config['admin_name'],
            'admin_bg_img' => $config['admin_bg_img']
        ];
        $codeData = (new SettingModel())->getLoginCode('admin_code');
        return $this->renderSuccess('', compact('settings', 'codeData'));
    }
}