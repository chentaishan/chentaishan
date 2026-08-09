<?php

namespace app\supplier\controller\live;

use app\supplier\controller\Controller;
use app\supplier\model\order\Order as OrderModel;
use app\supplier\model\live\Room as RoomModel;
use app\supplier\model\live\RoomGift as RoomGiftModel;
use app\supplier\model\live\LiveProduct as LiveProductModel;
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
        $list = $model->getList($this->postData(),$this->getSupplierId());
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
        $list = $model->getList($this->postData(),$this->getSupplierId());
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
     * 列表
     */
    public function getOrderList()
    {
        $model = new OrderModel();
        $list = $model->getAgentLiveOrder($this->postData(),$this->getSupplierId());
        return $this->renderSuccess('', compact('list'));
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
}