<?php

namespace app\common\model\plus\buy;

use app\common\model\BaseModel;

/**
 * 买送模型
 */
class BuyActivity extends BaseModel
{
    protected $pk = 'buy_id';
    protected $name = 'buy_activity';
    //附加字段
    protected $append = ['status_text', 'start_time_text', 'end_time_text'];

    /**
     * 关联供应商表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id')->field(['shop_supplier_id', 'name']);
    }

    /**
     * 商品列表
     * @return \think\model\relation\HasMany
     */
    public function limitProduct()
    {
        return $this->hasMany('app\\common\\model\\plus\\buy\\BuyActivityProduct', 'buy_id', 'buy_id');
    }

    /**
     * 赠送商品
     */
    public function getProductIdsAttr($value, $data)
    {
        return $value ? json_decode($value, true) : '';
    }

    /**
     * 有效期-开始时间
     */
    public function getStartTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['start_time']);
    }

    /**
     * 有效期-开始时间
     */
    public function getEndTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['end_time']);
    }

    /**
     * 状态
     * @param $val
     * @return string
     */
    public function getStatusTextAttr($value, $data)
    {
        if ($data['audit_status'] == 10) {
            return '待审核';
        }
        if ($data['audit_status'] == 30) {
            return '未通过';
        }
        if ($data['status'] == 0) {
            return '未开启';
        }
        if ($data['start_time'] > time()) {
            return '未开始';
        }
        if ($data['end_time'] < time()) {
            return '已结束';
        }
        if ($data['start_time'] < time() && $data['end_time'] > time()) {
            return '进行中';
        }
        return '';
    }

    /**
     * 获取列表记录
     */
    public function getList($data)
    {
        $model = $this;
        if (isset($data['name']) && $data['name']) {
            $model = $model->where('name', 'like', '%' . trim($data['name']) . '%');
        }
        if (isset($data['audit_status']) && $data['audit_status'] > 0) {
            $model = $model->where('audit_status', '=', $data['audit_status']);
        }
        if (isset($data['shop_supplier_id']) && $data['shop_supplier_id'] > 0) {
            $model = $model->where('shop_supplier_id', '=', $data['shop_supplier_id']);
        }
        return $model->with(['supplier'])
            ->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($data);
    }

    /**
     * 获取详情
     */
    public static function detail($buy_id)
    {
        return self::with(['limit_product'])->find($buy_id);
    }

    /**
     * 软删除
     */
    public function setDelete()
    {
        return $this->save(['is_delete' => 1]);
    }

    /**
     * 状态设置
     */
    public function setState($status)
    {
        return $this->save(['status' => $status]);
    }

}