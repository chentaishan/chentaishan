<?php

namespace app\supplier\controller;

use app\common\model\settings\Setting as SettingModel;
use app\supplier\service\ShopService;

/**
 * 后台首页控制器
 */
class Index extends Controller
{
    /**
     * 后台首页
     */
    public function index()
    {
        $service = new ShopService($this->getSupplierId());
        return $this->renderSuccess('', ['data' => $service->getHomeData($this->postData())]);
    }

    /**
     * 登录数据
     */
    public function base()
    {
        $config = SettingModel::getSysConfig();
        $settings = [
            'supplier_name' => $config['supplier_name'],
            'supplier_bg_img' => $config['supplier_bg_img'],
            'supplier_logo_img' => $config['supplier_logo_img']
        ];
        $codeData = (new SettingModel())->getLoginCode('supplier_code');
        return $this->renderSuccess('', compact('settings', 'codeData'));
    }
}