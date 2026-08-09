<?php

namespace app\shop\controller\plus;

use app\shop\controller\Controller;
use app\shop\model\plus\buy\BuyActivity as BuyActivityModel;

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
        $list = $model->getList($this->postData());
        //商户列表
        $supplierList = $model->getSupplierList();
        return $this->renderSuccess('', compact('list', 'supplierList'));
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