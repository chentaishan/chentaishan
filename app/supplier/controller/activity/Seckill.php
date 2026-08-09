<?php

namespace app\supplier\controller\activity;

use app\common\model\product\Product as ProductModel;
use app\supplier\controller\Controller;
use app\supplier\model\plus\seckill\Active as ActiveModel;
use app\supplier\model\plus\seckill\Product as SeckillProductModel;
/**
 * 秒杀活动控制器
 */
class Seckill extends Controller
{
    /**
     * 列表
     */
    public function index()
    {
        $list = (new ActiveModel())->getList($this->postData());
        //排除id
        $exclude_ids = (new SeckillProductModel())->getExcludeIds($this->getSupplierId());
        return $this->renderSuccess('', compact('list', 'exclude_ids'));
    }

    /**
     * 报名
     */
    public function add($product_id, $seckill_activity_id)
    {
        if($this->request->isGet()){
            //商品详情
            $model = ProductModel::detail($product_id);
            //活动详情
            $active = ActiveModel::detail($seckill_activity_id);
            return $this->renderSuccess('', compact('model', 'active'));
        }
        $data = $this->postData();
        // 新增记录
        if (!(new SeckillProductModel())->checkProduct($product_id, $this->getSupplierId())) {
            return $this->renderError('商品已经参加活动了，请勿重复提交');
        }
        $model = new ActiveModel();
        if ($model->add($this->getSupplierId(), $data)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?:'添加失败');
    }

    /**
     * 列表
     */
    public function my()
    {
        $supplier = $this->supplier['user'];
        $list = (new SeckillProductModel())->getList($supplier['shop_supplier_id'], $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 报名
     */
    public function edit($seckill_product_id)
    {
        if($this->request->isGet()){
            //商品详情
            $model = SeckillProductModel::detail($seckill_product_id, ['active', 'product' => ['sku','image.file'], 'seckillSku']);
            return $this->renderSuccess('', compact('model'));
        }
        $data = $this->postData();
        // 新增记录
        $model = SeckillProductModel::detail($seckill_product_id);
        if ($model->edit($data)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?:'添加失败');
    }

    /**
     * 删除
     */
    public function del($seckill_product_id)
    {
        $model = new SeckillProductModel();
        if ($model->remove($seckill_product_id, $this->getSupplierId())) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?:'删除失败');
    }
}