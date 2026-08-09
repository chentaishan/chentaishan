<?php

namespace app\api\controller\plus\agent;

use app\api\controller\Controller;
use app\api\model\plus\agent\Setting;
use app\api\model\plus\agent\User as AgentUserModel;
use app\common\service\qrcode\PosterService;

/**
 * 推广二维码
 */
class Qrcode extends Controller
{
    // 当前用户
    private $user;
    // 分销商
    private $agent;
    // 分销设置
    private $setting;

    /**
     * 构造方法
     */
    public function initialize()
    {
        // 用户信息
        $this->user = $this->getUser();
        // 分销商用户信息
        $this->agent = AgentUserModel::detail($this->user['user_id']);
        // 分销商设置
        $this->setting = Setting::getAll();
    }

    /**
     * 获取推广二维码
     */
    public function poster($source)
    {
        $Qrcode = new PosterService($this->agent, $source);
        return $this->renderSuccess('', [
            // 二维码图片地址
            'qrcode' => $Qrcode->getImage(),
        ]);
    }

}