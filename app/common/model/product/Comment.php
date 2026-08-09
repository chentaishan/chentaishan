<?php

namespace app\common\model\product;

use app\common\model\BaseModel;
use app\common\model\supplier\Supplier as SupplierModel;

/**
 * 评论模型
 */
class Comment extends BaseModel
{
    protected $name = 'comment';
    protected $px = 'comment_id';

    /**
     * 所属订单
     */
    public function orderM()
    {
        return $this->belongsTo('app\\common\\model\\order\\Order');
    }

    /**
     * 订单商品
     */
    public function OrderProduct()
    {
        return $this->belongsTo('app\\common\\model\\order\\OrderProduct');
    }

    /**
     * 商品
     */
    public function product()
    {
        return $this->belongsTo('app\\common\\model\\product\\Product', 'product_id', 'product_id');
    }

    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id');
    }

    /**
     * 关联商户表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id')->field(['shop_supplier_id', 'name']);
    }

    /**
     * 关联评价图片表
     */
    public function image()
    {
        return $this->hasMany('app\\common\\model\\product\\CommentImage', 'comment_id', 'comment_id')->order(['id' => 'asc']);
    }

    /**
     * 评价详情
     */
    public static function detail($comment_id)
    {
        return (new static())->where('comment_id', '=', $comment_id)->with(['user', 'image.file', 'orderM', 'product.image.file'])->find();
    }

    /**
     * 获取评价列表
     * @param $params
     * @return \think\Paginator
     * @throws \think\db\exception\DbException
     */
    public function getList($params)
    {
        $model = $this;
        // 检索查询条件
        $model = $model->setWhere($model, $params);
        return $model->with(['user', 'orderM', 'product.image.file', 'image.file', 'supplier'])
            ->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 获取评论数
     */
    public function getStatusNum($params)
    {
        $model = $this;
        // 检索查询条件
        $model = $model->setWhere($model, $params);
        return $model->where('is_delete', '=', 0)->count();
    }

    /**
     * 设置查询条件
     */
    public function setWhere($model, $params)
    {
        if (isset($params['name']) && !empty(trim($params['name']))) {
            $product_model = new Product();
            $res = $product_model->getWhereData($params['name'])->toArray();
            $str = implode(',', array_column($res, 'product_id'));
            $model = $model->where('product_id', 'in', $str);
        }
        if (isset($params['score']) && $params['score'] > 0) {
            $model = $model->where('score', '=', $params['score']);
        }
        if (isset($params['status']) && $params['status'] > -1) {
            $model = $model->where('status', '=', $params['status']);
        }
        if (isset($params['shop_supplier_id']) && $params['shop_supplier_id'] > 0) {
            $model = $model->where('shop_supplier_id', '=', $params['shop_supplier_id']);
        }
        return $model;
    }

    //更新店铺评分
    public function updateScore($shop_supplier_id)
    {
        $SupplierModel = new SupplierModel;
        $express = $this->where(['shop_supplier_id' => $shop_supplier_id, 'status' => 1, 'is_delete' => 0])
            ->field('round(avg(express_score),1) as score')->find();
        $server = $this->where(['shop_supplier_id' => $shop_supplier_id, 'status' => 1, 'is_delete' => 0])
            ->field('round(avg(server_score),1) as score')->find();
        $describe = $this->where(['shop_supplier_id ' => $shop_supplier_id, 'status' => 1, 'is_delete' => 0])
            ->field('round(avg(describe_score),1) as score')->find();
        $express_score = $express['score'] ? $express['score'] : 5;
        $server_score = $server['score'] ? $server['score'] : 5;
        $describe_score = $describe['score'] ? $describe['score'] : 5;
        //更新店铺分数
        $SupplierModel->where('shop_supplier_id', '=', $shop_supplier_id)
            ->update([
                'express_score' => $express_score,
                'server_score' => $server_score,
                'describe_score' => $describe_score
            ]);

    }

}