<?php

namespace app\supplier\controller\activity;

use app\supplier\controller\Controller;
use app\supplier\model\plus\buy\BuyActivity as BuyActivityModel;

/**
 * 买送
 */
class Buyactivity extends Controller
{
    /**
     * 列表
     */
    public function index()
    {
        $model = new BuyActivityModel;
        $data = $this->postData();
        $data['shop_supplier_id'] = $this->getSupplierId();
        $list = $model->getList($data);
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 添加
     */
    public function add()
    {
        $model = new BuyActivityModel;
        // 新增记录
        if ($model->add($this->postData(), $this->getSupplierId())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 编辑
     */
    public function edit($buy_id)
    {
        $model = BuyActivityModel::detail($buy_id);
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }
        // 修改记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 删除
     */
    public function delete($buy_id)
    {
        // 会员等级详情
        $model = BuyActivityModel::detail($buy_id);
        if ($model->setDelete()) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError('操作失败');
    }

    /**
     * 活动状态设置
     */
    public function state($buy_id, $status)
    {
        // 商品详情
        $model = BuyActivityModel::detail($buy_id);
        if (!$model->setState($status)) {
            return $this->renderError('操作失败');
        }
        return $this->renderSuccess('操作成功');
    }
}