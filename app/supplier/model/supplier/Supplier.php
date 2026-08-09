<?php

namespace app\supplier\model\supplier;

use app\common\model\supplier\Supplier as SupplierModel;
use app\common\model\product\Product as ProductModel;

/**
 * 后台管理员登录模型
 */
class Supplier extends SupplierModel
{
    /*
    * 修改密码
    */
    public function editPass($data, $user)
    {
        $user_info = User::detail($user['shop_user_id']);
        if ($data['password'] != $data['confirmPass']) {
            $this->error = '密码错误';
            return false;
        }
        if ($user_info['password'] != salt_hash($data['oldpass'])) {
            $this->error = '两次密码不相同';
            return false;
        }
        $date['password'] = salt_hash($data['password']);
        $user_info->save($date);
        return true;
    }

    /**
     * 修改
     */
    public function edit($data)
    {
        $isexist = $this->where('name', '=', $data['name'])->where('shop_supplier_id', '<>', $data['shop_supplier_id'])->find();
        if ($isexist) {
            $this->error = '店铺名称已存在';
            return false;
        }
        $isShow = isset($data['is_show']) ? ((int)$data['is_show'] > 0 ? 1 : 0) : (int)($this['is_show'] ?? 1);
        $latitude = isset($data['latitude']) && $data['latitude'] !== '' ? (string)$data['latitude'] : (string)($this['latitude'] ?? '0');
        $longitude = isset($data['longitude']) && $data['longitude'] !== '' ? (string)$data['longitude'] : (string)($this['longitude'] ?? '0');
        // 配送方式排序
        sort($data['logistics_type']);
        if (count($data['logistics_type']) == 1) {
            //更新商品配送方式
            (new ProductModel)->where('is_delete', '=', 0)
                ->where('shop_supplier_id', '=', $data['shop_supplier_id'])
                ->update(['logistics' => implode(',', $data['logistics_type'])]);
        }
        return $this->save([
            'name' => $data['name'],
            'link_phone' => $data['link_phone'],
            'address' => $data['address'],
            'description' => $data['description'],
            'logo_id' => $data['logo_id'],
            'back_image' => $data['back_image'],
            'logistics_type' => $data['logistics_type'],
            'is_show' => $isShow,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_full' => 1
        ]);
    }

    /**
     * 资金冻结
     */
    public function freezeMoney($money)
    {
        return $this->save([
            'money' => $this['money'] - $money,
            'freeze_money' => $this['freeze_money'] + $money,
        ]);
    }
}