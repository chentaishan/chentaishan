<?php

namespace app\api\controller\plus\lottery;

use app\api\controller\Controller;
use app\api\model\plus\lottery\Record as RecordModel;

/**
 *
 * 转盘商品订单控制器
 *
 */
class Order extends Controller
{
    /**
     * 记录详情
     */
    public function buy($record_id)
    {
        $detail = RecordModel::detail($record_id);
        $user = $this->getUser();
        $model = new RecordModel();
        if ($this->request->isGet()) {
            if ($detail['prize_type'] != 3) {
                return $this->renderError('礼品类型错误');
            }
            if ($detail['status'] == 1) {
                return $this->renderError('礼品已兑换');
            }
            $data['detail'] = $detail;
            $data['address'] = $user['address_default'];
            $data['exist_address'] = $user['address_id'] > 0;
            return $this->renderSuccess('', compact('data'));
        }
        if ($model->addOrder($record_id, $this->getUser())) {
            return $this->renderSuccess('兑换成功');
        }
        return $this->renderError($model->getError() ?: '兑换失败');

    }
}