<?php

namespace app\common\model\supplier;

use app\common\enum\settings\DeliveryTypeEnum;
use app\common\model\BaseModel;
use app\common\model\supplier\User as SupplierUserModel;

/**
 * 商家供应商模型
 */
class Supplier extends BaseModel
{
    protected $name = 'supplier';
    protected $pk = 'shop_supplier_id';

    /**
     * 设置配送方式
     * @param $value
     * @return array
     */
    public function setLogisticsTypeAttr($value)
    {
        return $value ? implode(',', $value) : '';
    }

    /**
     * 获取配送方式
     * @param $value
     * @return array
     */
    public function getLogisticsTypeAttr($value)
    {
        return $value ? explode(',', $value) : [];
    }

    /**
     * 关联应用表
     */
    public function app()
    {
        return $this->belongsTo('app\\common\\model\\app\\App', 'app_id', 'app_id');
    }

    /**
     * 关联logo
     */
    public function logo()
    {
        return $this->hasOne('app\\common\\model\\file\\UploadFile', 'file_id', 'logo_id');
    }

    /**
     * 关联品牌类型
     */
    public function category()
    {
        return $this->hasOne('app\\common\\model\\supplier\\Category', 'category_id', 'category_id');
    }

    /**
     * 关联business
     */
    public function business()
    {
        return $this->hasOne('app\\common\\model\\file\\UploadFile', 'file_id', 'business_id');
    }

    /**
     * 关联超管
     */
    public function superUser()
    {
        return $this->hasOne('app\\common\\model\\supplier\\User', 'shop_supplier_id', 'shop_supplier_id')
            ->where('is_super', '=', 1)->where('is_delete', '=', 0);
    }

    /**
     * 详情
     */
    public static function detail($shop_supplier_id, $with = [])
    {
        return (new static())->with($with)->find($shop_supplier_id);
    }

    /**
     * 累积供应商结算金额 (批量)
     */
    public function onBatchIncSupplierMoney($data)
    {
        foreach ($data as $supplierId => $supplierMoney) {
            $this->where(['shop_supplier_id' => $supplierId])
                ->inc('total_money', $supplierMoney)
                ->inc('money', $supplierMoney)
                ->update();
        }
        return true;
    }

    /**
     * 添加
     */
    public function addData($data)
    {
        // 开启事务
        $this->startTrans();
        try {
            if (SupplierUserModel::checkExist($data['user_name'])) {
                $this->error = '用户名已存在';
                return false;
            }
            // 添加供应商
            $this->save($data);
            //添加供应商账号
            $SupplierUserModel = new SupplierUserModel;
            $data['shop_supplier_id'] = $this['shop_supplier_id'];
            $data['is_super'] = 1;
            $SupplierUserModel->save($data);
            $this->commit();
            return $this['shop_supplier_id'];
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 获取商户列表数据
     */
    public static function getAll()
    {
        $model = new static();
        // 查询列表数据
        return $model->field(['shop_supplier_id,name'])
            ->where('is_delete', '=', 0)
            ->order(['create_time' => 'asc'])
            ->select();
    }

    /**
     * 获取商户配送方式
     */
    public function getLogistics($shop_supplier_id)
    {
        // 配送方式
        $logistics = DeliveryTypeEnum::logistics();
        //商户配送方式
        $logistics_type = self::detail($shop_supplier_id)['logistics_type'];
        $logisticsType = [];
        foreach ($logistics as $item) {
            if (in_array($item['value'], $logistics_type)) {
                $logisticsType[] = $item;
            }
        }
        return $logisticsType;
    }
}