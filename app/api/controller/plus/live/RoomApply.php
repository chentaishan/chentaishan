<?php

namespace app\api\controller\plus\live;

use app\api\controller\Controller;
use app\api\model\order\Order as OrderModel;
use app\api\model\plus\live\Room as RoomModel;
use app\api\model\plus\live\LiveProduct as LiveProductModel;
use app\api\model\product\Product as ProductModel;
use app\api\model\supplier\Category as CategoryModel;

/**
 * 主播直播申请
 */
class RoomApply extends Controller
{
    // 当前用户
    private $user;

    /**
     * 构造方法
     */
    public function initialize()
    {
        $this->user = $this->getUser();   // 用户信息
    }

    /**
     * 微信直播列表
     */
    public function lists()
    {
        $model = new RoomModel();
        $list = $model->getMyList($this->user, $this->postData(), true);
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 保存
     */
    public function addlive()
    {
        $model = new RoomModel;
        if ($model->add($this->user, $this->postData())) {
            $room_id = $model['room_id'];
            return $this->renderSuccess('保存成功', compact('room_id'));
        }
        return $this->renderError($model->getError() ?: '保存失败');
    }

    /**
     * 预告
     */
    public function addnotice()
    {
        $model = new RoomModel;
        if ($model->notice($this->user, $this->postData())) {
            $room_id = $model['room_id'];
            return $this->renderSuccess('保存成功', compact('room_id'));
        }
        return $this->renderError($model->getError() ?: '保存失败');
    }

    /**
     * 修改查询
     */
    public function detail($room_id)
    {
        $model = RoomModel::detailByUser($this->user['user_id'], $room_id);
        return $this->renderSuccess('', compact('model'));
    }

    /**
     * 保存
     */
    public function edit($room_id)
    {
        $model = RoomModel::detailByUser($this->user['user_id'], $room_id);
        if ($model->edit($this->user, $this->postData())) {
            return $this->renderSuccess('保存成功');
        }
        return $this->renderError($model->getError() ?: '保存失败');
    }

    /**
     * 保存商品
     */
    public function addProduct($productIds)
    {
        $model = new LiveProductModel();
        if ($model->add($this->user, $productIds)) {
            return $this->renderSuccess('保存成功');
        }
        return $this->renderError($model->getError() ?: '保存失败');
    }

    /**
     * 删除商品
     */
    public function delProduct($product_id)
    {

        $model = new LiveProductModel();
        if ($model->remove($product_id)) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    /**
     * 删除
     */
    public function delete($room_id)
    {
        $model = RoomModel::detailByUser($this->user['user_id'], $room_id);
        if ($model->setDelete($this->user, $this->postData())) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    /**
     * 直播商品列表
     */
    public function liveproduct()
    {
        $model = new LiveProductModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 订单列表
     */
    public function orderList()
    {
        $model = new OrderModel();
        $list = $model->getLiveOrder($this->postData(), $this->user);
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 供应商商品列表
     */
    public function product_list()
    {
        $model = new ProductModel();
        //查找商品
        $param = $this->postData();
        $param['shop_supplier_id'] = $this->user['supplierUser']['shop_supplier_id'];
        $param['product_id'] = (new LiveProductModel())->livProduct($param['shop_supplier_id']);
        $list = $model->getList($param, $this->getUser());
        return $this->renderSuccess('', compact('list'));
    }

    //分类列表
    public function category()
    {
        $list = CategoryModel::getALL();
        return $this->renderSuccess('', compact('list'));
    }

}