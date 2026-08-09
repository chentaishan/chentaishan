<?php

namespace app\shop\controller\plus;

use app\shop\controller\Controller;
use app\shop\model\plus\lottery\Lottery as LotteryModel;
use app\shop\model\plus\lottery\Record as RecordModel;
use app\shop\model\plus\lottery\LotteryPrize as LotteryPrizeModel;
use app\shop\model\settings\Express as ExpressModel;
use app\shop\model\user\Grade as GradeModel;

/**
 * 转盘控制器
 */
class Lottery extends Controller
{
    /**
     *修改
     */
    public function setting()
    {
        $model = new LotteryModel();
        if ($this->request->isGet()) {
            $data = $model->getLottery();
            $data['prize'] = $data ? LotteryPrizeModel::detail($data['lottery_id']) : [];
            // 会员等级列表
            $gradeList = GradeModel::getUsableList();
            return $this->renderSuccess('', compact('data', 'gradeList'));
        }
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     * 转盘记录列表
     */
    public function record()
    {
        $model = new RecordModel();
        $list = $model->getList($this->postData());
        // 物流公司列表
        $ExpressModel = new ExpressModel();
        $expressList = $ExpressModel->getAll();
        $lotteryType = $model->getLotteryType();
        return $this->renderSuccess('', compact('list', 'expressList', 'lotteryType'));
    }

    /**
     * 获取下架奖项
     * @param null $id
     */
    public function award()
    {
        $model = new LotteryModel();
        $data = $model->getLottery();
        $list = LotteryPrizeModel::getlist($this->postData(), $data);
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 发货
     */
    public function send($record_id)
    {
        $model = RecordModel::detail($record_id);
        if ($model->send($this->postData())) {
            return $this->renderSuccess('发货成功');
        }
        return $this->renderError($model->getError() ?: '发货失败');
    }

    /**
     * 抽奖记录导出
     */
    public function export()
    {
        $model = new RecordModel();
        return $model->exportList($this->postData());
    }

    /**
     * 备注
     */
    public function remark()
    {
        $model = new RecordModel();
        if ($model->setRemark($this->postData())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 查看物流
     */
    public function express($record_id)
    {
        $detail = RecordModel::detail($record_id, ['express']);
        if (!$detail || !$detail['express_no']) {
            return $this->renderError('没有物流信息');
        }
        // 获取物流信息
        $model = $detail['express'];
        $express = $model->dynamic($model['express_name'], $model['express_code'], $detail['express_no'], $detail['phone']);
        if ($express === false) {
            return $this->renderError($model->getError());
        }
        return $this->renderSuccess('', compact('express'));
    }
}