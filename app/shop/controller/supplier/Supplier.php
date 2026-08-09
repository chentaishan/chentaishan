<?php

namespace app\shop\controller\supplier;

use app\shop\controller\Controller;
use app\shop\model\supplier\Supplier as SupplierModel;
use app\shop\model\supplier\Apply as ApplyModel;
use app\shop\model\supplier\Category as CategoryModel;
use app\shop\model\supplier\DepositRefund as DepositRefundModel;
use app\shop\model\supplier\ServiceApply as ServiceApplyModel;

/**
 * 商户控制器
 */
class Supplier extends Controller
{

    /**
     * 商户列表
     */
    public function index()
    {
        // 供应商列表
        $model = new SupplierModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 添加商户
     */
    public function add()
    {
        $model = new SupplierModel;
        $category = CategoryModel::getALL();
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('category'));
        }
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('', '添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }


    /**
     * 编辑商户
     */
    public function edit($shop_supplier_id)
    {
        $model = SupplierModel::detail($shop_supplier_id, ['logo', 'business', 'superUser.user']);
        $category = CategoryModel::getALL();
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model', 'category'));
        }
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('', '更新成功');
        }
        return $this->renderError($model->getError() ?: '更新失败');
    }

    /**
     * 删除商户
     */
    public function delete($shop_supplier_id)
    {
        // 店员详情
        $model = SupplierModel::detail($shop_supplier_id);
        if (!$model->setDelete()) {
            return $this->renderError('删除失败');
        }
        return $this->renderSuccess('', $model->getError() ?: '删除成功');
    }

    /**
     * 供应商待审核
     */
    public function apply()
    {
        // 供应商列表
        $model = new ApplyModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 供应商待审核详情
     */
    public function audit($supplier_apply_id)
    {

        $model = ApplyModel::detail($supplier_apply_id, ['businessImage', 'idcardFrontImage', 'idcardBackImage', 'user', 'category']);
        if ($model) {
            $model['business_image_path'] = $model['businessImage']['file_path'] ?? '';
            $model['idcard_front_path'] = $model['idcardFrontImage']['file_path'] ?? '';
            $model['idcard_back_path'] = $model['idcardBackImage']['file_path'] ?? '';
        }
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }
        if ($model->audit($this->postData())) {
            return $this->renderSuccess('', '操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败', []);
    }

    /**
     * 退押金列表
     */
    public function refund()
    {
        $model = new DepositRefundModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 退押金审核
     */
    public function submit($deposit_refund_id)
    {
        $model = DepositRefundModel::detail($deposit_refund_id);
        if ($model->submit($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 服务保障申请列表
     */
    public function security()
    {
        $model = new ServiceApplyModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 服务保障审核
     */
    public function verify($service_apply_id)
    {
        $model = ServiceApplyModel::getdetail($service_apply_id);
        if ($model->verify($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 开启禁止
     */
    public function recycle($shop_supplier_id, $is_recycle)
    {
        // 商品详情
        $model = SupplierModel::detail($shop_supplier_id);
        if (!$model->setRecycle($is_recycle)) {
            return $this->renderError('操作失败');
        }
        return $this->renderSuccess('操作成功');
    }
}
