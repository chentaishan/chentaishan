<?php

namespace app\shop\controller\plus\live;

use app\shop\controller\Controller;
use app\shop\model\plus\live\Room as RoomModel;
use app\shop\model\plus\live\RoomGift as RoomGiftModel;
use app\shop\model\plus\live\LiveProduct as LiveProductModel;
use app\common\model\plus\live\UserGift as UserGiftModel;
/**
 * 房间控制器
 */
class Room extends Controller
{

    /**
     * 列表
     */
    public function index()
    {
        $model = new RoomModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 礼物列表
     */
    public function gift()
    {
        $model = new RoomGiftModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 商品列表
     */
    public function product()
    {
        $model = new LiveProductModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }


    /**
     * 删除
     */
    public function delete($room_id)
    {
        // 详情
        $model = new RoomModel;
        // 更新记录
        if ($model->setDelete(['room_id' => $room_id])) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError('删除失败');
    }

    /**
     * 修改
     */
    public function edit($room_id)
    {
        // 详情
        $model = RoomModel::detail($room_id);
        // 更新记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('设置成功');
        }
        return $this->renderError('设置失败');
    }
    /**
     * 礼物排行
     */
    public function user_gift()
    {
        $model = new UserGiftModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }
    /**
     * 审核
     */
    public function audit($room_id)
    {
        // 直播详情
        $model = RoomModel::detail($room_id);
        if ($model->audit($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?:'操作失败');
    }
}