<?php

namespace app\common\model\settings;

use app\common\model\BaseModel;

/**
 * 电子面单设置
 */
class DeliverySetting extends BaseModel
{
    protected $name = 'delivery_setting';
    protected $pk = 'setting_id';

    /**
     * 关联物流公司表
     * @return \think\model\relation\BelongsTo
     */
    public function express()
    {
        return $this->belongsTo('app\\common\\model\\settings\\Express', 'express_id', 'express_id');
    }

    /**
     * 获取全部
     */
    public static function getAll($shop_supplier_id = 0)
    {
        $model = new static;
        if ($shop_supplier_id > 0) {
            $model = $model->where('shop_supplier_id', '=', $shop_supplier_id);
        }
        return $model->order(['sort' => 'asc'])->where('is_delete', '=', 0)->select();
    }

    /**
     * 获取列表
     */
    public function getList($data, $shop_supplier_id = 0)
    {
        $model = $this;
        if ($shop_supplier_id > 0) {
            $model = $model->where('shop_supplier_id', '=', $shop_supplier_id);
        }
        return $model->with(['express'])->order(['sort' => 'asc'])
            ->where('is_delete', '=', 0)
            ->paginate($data);
    }

    /**
     * 模板详情
     * @param $template_id
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function detail($setting_id, $with = [])
    {
        return (new static())->with($with)->find($setting_id);
    }
}
