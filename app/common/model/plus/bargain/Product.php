<?php

namespace app\common\model\plus\bargain;

use app\common\model\BaseModel;

/**
 * 砍价商品模型
 * @package app\common\model\plus\bargain
 */
class Product extends BaseModel
{
    protected $name = 'bargain_product';
    protected $pk = 'bargain_product_id';

    protected $append = ['product_sales', 'status_text'];

    /**
     * 计算显示销量 (初始销量 + 实际销量)
     */
    public function getProductSalesAttr($value, $data)
    {
        return $data['sales_initial'] + $data['total_sales'];
    }

    /**
     * 状态
     */
    public function getStatusTextAttr($value, $data)
    {
        if ($data['status'] == 0) {
            return '待审核';
        }
        if ($data['status'] == 10) {
            return '通过';
        }
        if ($data['status'] == 20) {
            return '未通过';
        }
        return '';
    }

    /**
     *关联商品主表
     */
    public function product()
    {
        return $this->hasOne('app\\common\\model\\product\\Product', 'product_id', 'product_id');
    }

    /**
     * 详情
     */
    public static function detail($bargain_product_id, $with = [])
    {
        return (new static())->with($with)->find($bargain_product_id);
    }

    /**
     *关联商品规格表
     */
    public function bargainSku()
    {
        return $this->hasMany('app\\common\\model\\plus\\bargain\\BargainSku', 'bargain_product_id', 'bargain_product_id');
    }

    /**
     *关联活动表
     */
    public function active()
    {
        return $this->belongsTo('app\\common\\model\\plus\\bargain\\Active', 'bargain_activity_id', 'bargain_activity_id');
    }

    /**
     * 关联供应商
     */
    public function supplier()
    {
        return $this->hasMany('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id');
    }

    /**
     * 商品ID是否存在
     */
    public static function isExistProductId($productId)
    {
        return (new static)->alias('p')
            ->join('bargain_activity a', 'a.bargain_activity_id=p.bargain_activity_id')
            ->where('a.is_delete', '=', 0)
            ->where('p.product_id', '=', $productId)
            ->where('p.is_delete', '=', 0)
            ->value('bargain_product_id');
    }
}