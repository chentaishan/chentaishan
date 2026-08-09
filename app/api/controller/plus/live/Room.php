<?php

namespace app\api\controller\plus\live;

use app\api\controller\Controller;
use app\api\model\plus\live\Room as RoomModel;
use app\api\model\plus\live\Gift as GiftModel;
use app\api\model\product\Product as ProductModel;
use app\api\model\user\Favorite as FavoriteModel;
use app\api\model\settings\Setting as SettingModel;
use app\common\service\qrcode\RoomService;
use app\common\model\plus\live\UserGift as UserGiftModel;

/**
 * 房间控制器
 */
class Room extends Controller
{
    /**
     * 微信直播列表
     */
    public function lists()
    {
        $model = new RoomModel();
        $list = $model->getList($this->postData(), $this->getUser(false));
        $live = $model->getLive($this->postData());
        return $this->renderSuccess('', compact('list', 'live'));
    }

    /**
     * 礼物列表
     */
    public function gift()
    {
        $model = new GiftModel();
        $list = $model->getList();
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 发送礼物
     */
    public function send_gift($room_id, $gift_id)
    {
        $model = new GiftModel();
        if ($model->sendGift($this->getUser(), $room_id, $gift_id)) {
            // 重新获取用户礼物数量
            $gift_money = $this->getUser()['gift_money'];
            // 房间获取的总礼物数
            $gift_num = RoomModel::detail($room_id)['gift_num'];
            return $this->renderSuccess('发送成功', compact('gift_money', 'gift_num'));
        }
        return $this->renderError($model->getError() ?: '发送失败');
    }

    /**
     * 点赞
     */
    public function digg($room_id, $num)
    {
        $model = RoomModel::detail($room_id);
        if ($model->digg($num)) {
            $digg_num = $model['digg_num'] + 1;
            return $this->renderSuccess('点赞成功', compact('digg_num'));
        }
        return $this->renderError($model->getError() ?: '点赞失败');
    }

    /**
     * 当前房间详情
     */
    public function detail($room_id)
    {
        $model = RoomModel::detail($room_id, ['cover', 'product.product.image.file', 'user', 'share', 'currentProduct.image.file', 'supplier.logo']);
        // 是否关注过
        $hasFollow = false;
        $fans = $this->getUser();
        if ($fans && $fans['user_id'] != $model['user_id']) {
            $hasFollow = FavoriteModel::isFollow($model['shop_supplier_id'], $fans['user_id'], 10) ? true : false;
        }
        $gift_name = SettingModel::getItem('live')['gift_name'];
        $user = [
            'nickName' => $fans['nickName'],
            'avatarUrl' => $fans['avatarUrl'],
        ];
        $isCaster = false;
        if ($model['user_id'] == $fans['user_id']) {
            $isCaster = true;
        }
        return $this->renderSuccess('', compact('model', 'hasFollow', 'gift_name', 'user', 'isCaster'));
    }

    /**
     * 同步房间详情
     */
    public function syn_room($room_id)
    {
        $detail = RoomModel::detail($room_id);
        $detail->updateEndTime();
        $model = [
            'views' => $detail['show_views'],
            'digg_num' => $detail['digg_num'],
        ];
        return $this->renderSuccess('', compact('model'));
    }

    /**
     * 设置商品
     */
    public function set_product($room_id, $product_id)
    {
        $user = $this->getUser();
        $model = RoomModel::detailByUser($user['user_id'], $room_id);
        if ($model->setProduct($product_id)) {
            return $this->renderSuccess('设置成功');
        }
        return $this->renderError($model->getError() ?: '设置失败');
    }

    /**
     * 设置商品
     */
    public function product_detail($product_id)
    {
        $model = ProductModel::detail($product_id);
        return $this->renderSuccess('', compact('model'));
    }

    /**
     * 设置状态
     */
    public function set_status($room_id, $status)
    {
        $user = $this->getUser();
        $model = RoomModel::detailByUser($user['user_id'], $room_id);
        if ($model->setStatus($status)) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     * 生成直播海报
     */
    public function poster($room_id, $source)
    {
        // 商品详情
        $detail = RoomModel::detail($room_id, ['user', 'share']);
        $Qrcode = new RoomService($detail, $this->getUser(), $source);
        return $this->renderSuccess('', [
            'qrcode' => $Qrcode->getImage(),
        ]);
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
     * 直播数据
     */
    public function livedata()
    {
        $user = $this->getUser();
        $model = new RoomModel();
        $data = $model->livedata($user['supplierUser']['shop_supplier_id']);
        return $this->renderSuccess('', compact('data'));
    }
}