<?php

namespace app\shop\model\plus\lottery;

use app\common\model\plus\lottery\Record as RecordModel;
use app\shop\service\order\ExportService;

/**
 * Class GiftPackage
 * 记录模型
 * @package app\common\model\plus\giftpackage
 */
class Record extends RecordModel
{
    /**
     * 状态
     */
    public function getDeliveryTimeAttr($value, $data)
    {
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    /**
     * 记录列表
     * @param $data
     */
    public function getList($data)
    {
        $model = $this;
        //搜索会员昵称
        if ($data['search'] != '') {
            $model = $model->where('nickName|mobile', 'like', '%' . trim($data['search']) . '%');
        }
        if (!empty($data['reg_date'][0])) {
            $model = $model->whereTime('r.create_time', 'between', [$data['reg_date'][0], date('Y-m-d 23:59:59', strtotime($data['reg_date'][1]))]);
        }
        if ($data['status'] != '' && $data['status'] > -1) {
            $model = $model->where('r.status', '=', $data['status']);
        }
        if (isset($data['record_name']) && $data['record_name']) {
            $model = $model->where('r.record_name', 'like', '%' . trim($data['record_name']) . '%');
        }
        if (isset($data['type']) && $data['type'] >= 0) {
            $model = $model->where('prize_type', '=', $data['type']);
        }
        return $model->alias('r')
            ->with(['user', 'express'])
            ->join('user u', 'r.user_id=u.user_id')
            ->field('r.*')
            ->order('r.create_time', 'desc')
            ->paginate($data);
    }

    /**
     * 发货
     */
    public function send($data)
    {
        if ($this['status'] != 1) {
            $this->error = '商品未兑换，不允许发货';
        }
        $deliver = [
            'express_id' => $data['express_id'],
            'express_no' => $data['express_no'],
            'delivery_status' => 20,
            'delivery_time' => time()
        ];
        return $this->save($deliver);

    }

    /**
     * 记录列表
     * @param $data
     */
    public function getListAll($data)
    {
        $model = $this;
        //搜索会员昵称
        if ($data['search'] != '') {
            $model = $model->where('nickName|mobile', 'like', '%' . trim($data['search']) . '%');
        }
        if (!empty($data['reg_date'][0])) {
            $model = $model->whereTime('r.create_time', 'between', [$data['reg_date'][0], date('Y-m-d 23:59:59', strtotime($data['reg_date'][1]))]);
        }
        if ($data['status'] != '' && $data['status'] > -1) {
            $model = $model->where('r.status', '=', $data['status']);
        }
        if (isset($data['record_name']) && $data['record_name']) {
            $model = $model->where('r.record_name', 'like', '%' . trim($data['record_name']) . '%');
        }
        if (isset($data['type']) && $data['type'] >= 0) {
            $model = $model->where('prize_type', '=', $data['type']);
        }
        return $model->alias('r')
            ->with(['user', 'express'])
            ->join('user u', 'r.user_id=u.user_id')
            ->field('r.*')
            ->order('r.create_time', 'desc')
            ->select();
    }

    /**
     * 订单导出
     */
    public function exportList($data)
    {
        // 获取订单列表
        $list = $this->getListAll($data);
        // 导出excel文件
        (new Exportservice)->lotteryList($list);
    }

    /**
     * 备注
     */
    public function setRemark($data)
    {
        $detail = self::detail($data['record_id']);
        if (!$detail) {
            $this->error = '抽奖记录不存在';
            return false;
        }
        return $detail->save(['remark' => $data['remark']]);
    }
}