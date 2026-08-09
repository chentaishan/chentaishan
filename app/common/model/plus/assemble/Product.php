<?php

namespace app\common\model\plus\assemble;

use app\common\model\BaseModel;

/**
 * 拼团商品
 */
class Product extends BaseModel
{
    protected $name = 'assemble_product';
    protected $pk = 'assemble_product_id';

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

    public static function detail($assemble_product_id, $with = ['product.sku', 'assembleSku'])
    {
        return (new static())->with($with)->where('assemble_product_id', '=', $assemble_product_id)->find();
    }

    public function active()
    {
        return $this->belongsTo('app\\common\\model\\plus\\assemble\\Active', 'assemble_activity_id', 'assemble_activity_id');
    }

    public function product()
    {
        return $this->belongsTo('app\\common\\model\\product\\Product', 'product_id', 'product_id');
    }

    /**
     *关联商品规格表
     */
    public function assembleSku()
    {
        return $this->hasMany('app\\common\\model\\plus\\assemble\\AssembleSku', 'assemble_product_id', 'assemble_product_id');
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
            ->join('assemble_activity a', 'a.assemble_activity_id=p.assemble_activity_id')
            ->where('a.is_delete', '=', 0)
            ->where('p.product_id', '=', $productId)
            ->where('p.is_delete', '=', 0)
            ->value('assemble_product_id');
    }
}