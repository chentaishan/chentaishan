<?php

namespace app\shop\controller\plus\live;

use app\shop\controller\Controller;
use app\shop\model\plus\live\WxLive as WxLiveModel;

/**
 * 微信小程序直播控制器
 */
class Wx extends Controller
{
    /**
     *直播列表
     */
    public function index()
    {
        $model = new WxLiveModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     *直播列表同步
     */
    public function syn()
    {
        $model = new WxLiveModel();
        if($model->syn()){
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');


    }

    /**
     * 修改直播间置顶状态
     */
    public function settop($live_id)
    {
        // 直播间详情
        $model = WxLiveModel::detail($live_id);
        if (!$model->setTop($this->postData())) {
            return $this->renderError('操作失败');
        }
        return $this->renderSuccess('操作成功');
    }
}