<?php

namespace app\api\model\plus\assemble;

use app\common\model\plus\assemble\Bill as BillModel;

class Bill extends BillModel
{
    /**
     * 获取正在拼团的订单
     */
    public function getBill($assemble_product_id, $assemble_activity_id, $assemble_num)
    {
        $filter = [
            'assemble_product_id' => $assemble_product_id,
            'assemble_activity_id' => $assemble_activity_id,
            'status' => 10
        ];
        $res = $this->with(['user','billUser'])
            ->where($filter)
            ->order(['create_time' => 'desc'])
            ->select();
        foreach ($res as $key => $val) {
            $res[$key]['dif_people'] = $assemble_num - $val['actual_people'];
        }
        return $res;
    }

}
