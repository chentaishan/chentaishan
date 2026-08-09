<?php

namespace app\api\controller\plus\live;

use app\api\controller\Controller;
use app\api\model\plus\live\WxLive as WxLiveModel;

/**
 * 微信直播控制器
 */
class Wx extends Controller
{
    /**
     * 微信直播列表
     */
    public function lists()
    {
        $model = new WxLiveModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

}