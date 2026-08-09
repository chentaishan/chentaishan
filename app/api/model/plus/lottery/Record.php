<?php

namespace app\api\model\plus\lottery;

use app\common\model\plus\lottery\Record as RecordModel;

/**
 * Class GiftPackage
 * 记录模型
 * @package app\common\model\plus\giftpackage
 */
class Record extends RecordModel
{
    /**
     * 记录列表
     * @param $data
     */
    public function getList($data, $user)
    {
        $model = $this;
        if (isset($data['type']) && $data['type'] >= 0) {
            $model = $model->where('prize_type', '=', $data['type']);
        }
        return $model->alias('r')
            ->where('user_id', '=', $user['user_id'])
            ->field('r.*')
            ->order('r.create_time', 'desc')
            ->paginate($data);
    }

    /**
     * 记录列表
     * @param $data
     */
    public function getLimitList($limit)
    {
        $model = $this;
        return $model->alias('r')
            ->with(['user'])
            ->field('r.*')
            ->where('is_play', '=', 1)
            ->order('r.create_time', 'desc')
            ->limit($limit)
            ->select();
    }

    //兑换商品
    public function addOrder($record_id, $user)
    {
        // 开启事务
        $this->startTrans();
        try {
            $detail = (new Record)->detail($record_id);
            if ($detail['prize_type'] != 3) {
                $this->error = '礼品类型错误';
                return false;
            }
            if ($detail['status'] == 1) {
                $this->error = '礼品已兑换';
                return false;
            }
            if (!$user['address_default']) {
                $this->error = '请选择收货地址';
                return false;
            }
            $address = $user['address_default'];
            $data = [
                'name' => $address['name'],
                'phone' => $address['phone'],
                'province_id' => $address['province_id'],
                'city_id' => $address['city_id'],
                'region_id' => $address['region_id'],
                'detail' => $address['detail'],
                'status' => 1
            ];
            //更新记录
            $detail->save($data);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }
}