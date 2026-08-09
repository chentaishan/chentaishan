<?php

namespace app\common\model\plus\advance;

use app\common\model\BaseModel;

/**
 * 预售商品模型
 */
class Product extends BaseModel
{
    protected $name = 'advance_product';
    protected $pk = 'advance_product_id';

    protected $append = ['product_sales', 'active_time'];

    /**
     * 计算显示销量 (初始销量 + 实际销量)
     */
    public function getProductSalesAttr($value, $data)
    {
        return $data['sales_initial'] + $data['total_sales'];
    }

    public function getActiveTimeAttr($value, $data)
    {
        return $data['start_time'] && $data['end_time'] ? [date("Y-m-d H:i:s", $data['start_time']), date("Y-m-d H:i:s", $data['end_time'])] : [];
    }

    public static function detail($advance_product_id, $with = ['product.sku', 'sku'])
    {
        return (new static())->with($with)->where('advance_product_id', '=', $advance_product_id)->find();
    }

    public function product()
    {
        return $this->belongsTo('app\\common\\model\\product\\Product', 'product_id', 'product_id');
    }

    public function sku()
    {
        return $this->hasMany('app\\common\\model\\plus\\advance\\AdvanceSku', 'advance_product_id', 'advance_product_id');
    }

    /**
     * 关联供应商
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id');
    }

    //查询预售商品详情
    public static function getProductAdvanceDetail($product_id)
    {
        $detail = (new static)->with(['product.image.file', 'sku.productSku.image'])
            ->where('product_id', '=', $product_id)
            ->where('end_time', '>', time())
            ->where('status', '=', 10)
            ->where('audit_status', '=', 20)
            ->where('is_delete', '=', 0)
            ->find();
        return $detail;
    }


}