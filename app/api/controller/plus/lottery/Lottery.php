<?php

namespace app\api\controller\plus\lottery;

use app\api\controller\Controller;
use app\api\model\plus\lottery\Lottery as LotteryModel;
use app\api\model\plus\lottery\Record as RecordModel;
use app\shop\model\plus\lottery\LotteryPrize as LotteryPrizeModel;

/**
 * 转盘控制器
 */
class Lottery extends Controller
{
    /**
     * 获取数据
     */
    public function getLottery()
    {
        $model = new LotteryModel();
        $data = $model->getDetail();
        //剩余抽奖次数
        $nums = $model->getNum($this->getUser(), $data);
        //抽奖播放数据
        $recordModel = new RecordModel();
        $recordList = $recordModel->getLimitList(60);
        $data['user_points'] = $this->getUser()['points'];
        return $this->renderSuccess('', compact('data', 'nums', 'recordList'));
    }

    /**
     * 转盘记录列表
     */
    public function record()
    {
        $model = new RecordModel();
        $list = $model->getList($this->postData(), $this->getUser());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 开始抽奖
     */
    public function draw()
    {
        $model = new LotteryModel();
        $result = $model->getdraw($this->getUser());
        if ($result) {
            return $this->renderSuccess('', compact('result'));
        }
        return $this->renderError($model->getError() ?: '抽奖失败');
    }

    /**
     * 查看物流
     */
    /**
     * 获取物流信息
     */
    public function express($record_id)
    {
        // 订单信息
        $detail = RecordModel::detail($record_id, ['express']);
        if (!$detail['express_no']) {
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