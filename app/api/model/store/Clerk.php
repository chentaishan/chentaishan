<?php

namespace app\api\model\store;

use app\common\exception\BaseException;
use app\common\model\store\Clerk as ClerkModel;

/**
 * 商家门店店员模型
 */
class Clerk extends ClerkModel
{
    /**
     * 隐藏字段
     */
    protected $hidden = [
        'is_delete',
        'app_id',
        'create_time',
        'update_time'
    ];

    /**
     * 店员详情
     */
    public static function detail($where)
    {
        $model = parent::detail($where);
        if (!$model) {
            throw new BaseException(['msg' => '未找到店员信息']);
        }
        return $model;
    }

    /**
     * 验证用户是否为核销员
     */
    public function checkUser($store_id)
    {
        if ($this['is_delete']) {
            $this->error = '未找到店员信息';
            return false;
        }
        if ($this['store_id'] != $store_id) {
            $this->error = '当前店员不属于该门店，没有核销权限';
            return false;
        }
        if (!$this['status']) {
            $this->error = '当前店员状态已被禁用';
            return false;
        }
        return true;
    }

    /**
     * 查询用户是否为核销员
     */
    public function isCheck($user)
    {
        return $this->alias('c')
            ->join('store s', 's.store_id=c.store_id')
            ->join('supplier sl', 'sl.shop_supplier_id=s.shop_supplier_id')
            ->where('c.user_id', '=', $user['user_id'])
            ->where('c.status', '=', 1)
            ->where('c.is_delete', '=', 0)
            ->where('s.is_check', '=', 1)
            ->where('s.status', '=', 1)
            ->where('s.is_delete', '=', 0)
            ->where('sl.status', '=', 0)
            ->where('sl.is_delete', '=', 0)
            ->where('sl.is_recycle', '=', 0)
            ->count();
    }

}