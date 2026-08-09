<?php

namespace app\shop\controller\user;

use app\shop\controller\Controller;
use app\shop\model\settings\Setting as SettingModel;
use app\shop\model\user\Cash as CashModel;

/**
 * 提现
 */
class Cash extends Controller
{
    /**
     * 提现记录列表
     */
    public function index()
    {
        $model = new CashModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 提现审核
     */
    public function audit($id)
    {
        $model = CashModel::detail($id);
        if ($model->submit($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 确认打款
     */
    public function money($id)
    {
        $model = CashModel::detail($id);

        if ($model->money()) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 余额提现：微信支付企业付款
     */
    public function wxpay($id)
    {
        $model = CashModel::detail($id);
        if ($model->wechatPay()) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 订单导出
     */
    public function export()
    {
        $model = new CashModel();
        return $model->exportList($this->postData());
    }

    /**
     * 提现设置
     */
    public function setting()
    {
        if ($this->request->isGet()) {
            $values = SettingModel::getItem('balance_cash');
            $pay_type = (new CashModel)->getPayType();
            return $this->renderSuccess('', compact('values', 'pay_type'));
        }
        $model = new SettingModel;
        if ($model->edit('balance_cash', $this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 撤销微信付款
     */
    public function cancel($id)
    {
        $model = CashModel::detail($id);
        if ($model->cancelPay()) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }
}