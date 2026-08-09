<?php


namespace app\common\model\supplier;

use app\common\model\BaseModel;

/**
 * 供应商服务保障模型
 */
class ServiceSecurity extends BaseModel
{
    protected $pk = 'service_security_id';
    protected $name = 'supplier_service_security';
    /**
     * 所有
     * @return \think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getALL($map=[])
    {
        $model = new static;
        if($map){
            $model=$model->where($map);
        }
        return $model->order(['create_time' => 'asc'])->select();
    }
    /**
     * 详情
     */
    public static function detail($service_security_id)
    {
        return (new static())->find($service_security_id);
    }
}