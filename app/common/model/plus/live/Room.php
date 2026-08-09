<?php

namespace app\common\model\plus\live;

use app\common\model\BaseModel;

/**
 * 房间模型
 */
class Room extends BaseModel
{
    protected $name = 'live_room';
    protected $pk = 'room_id';

    protected $append = ['show_views', 'status_text', 'start_time_text', 'end_time_text', 'real_start_time_text', 'real_end_time_text'];

    /**
     * 计算显示人数 (虚拟人数 + 实际人数)
     */
    public function getShowViewsAttr($value, $data)
    {
        return $data['view_num'] + $data['virtual_num'];
    }

    /**
     * 直播间状态。101：直播中，102：未开始，103已结束，104：暂停，107：已过期
     */
    public function getStatusTextAttr($value, $data)
    {
        $liveStatus = [0=>'待审核',100=>'未通过',101 => '直播中', 102 => '未开始', 103 => '已结束', 104 => '暂停', 107 => '已过期'];
        return $liveStatus[$data['live_status']];
    }

    /**
     * 直播开始时间
     */
    public function getStartTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['start_time']);
    }

    /**
     * 直播结束时间
     */
    public function getEndTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['end_time']);
    }
    /**
     * 直播开始时间
     */
    public function getRealStartTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['real_start_time']);
    }

    /**
     * 直播结束时间
     */
    public function getRealEndTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['real_end_time']);
    }
    /**
     * 关联商品图片表
     */
    public function product()
    {
        return $this->hasMany('app\\common\\model\\plus\\live\\LiveProduct' ,'shop_supplier_id', 'shop_supplier_id')->order(['live_product_id' => 'asc']);
    }

    /**
     * 关联当前商品图片表
     */
    public function currentProduct()
    {
        return $this->belongsTo('app\\common\\model\\product\\Product', 'product_id', 'product_id');
    }
    /**
     * 关联封面图
     */
    public function cover()
    {
        return $this->hasOne('app\\common\\model\\file\\UploadFile', 'file_id', 'cover_img_id');
    }

    /**
     * 关联创建者
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id')
            ->field(['user_id', 'nickName', 'avatarUrl']);
    }

    /**
     * 关联封面图
     */
    public function share()
    {
        return $this->hasOne('app\\common\\model\\file\\UploadFile', 'file_id', 'share_img_id');
    }

    /**
     * 详情
     */
    public static function detail($room_id, $with = [])
    {
        return (new static())->with($with)->find($room_id);
    }

    /**
     * 详情
     */
    public static function detailByUser($user_id, $room_id, $with = ['cover', 'share'])
    {
        return (new static())->with($with)->where('room_id', '=', $room_id)
            ->where('user_id', '=', $user_id)
            ->find();
    }
    /**
     * 关联供应商表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id')->field(['shop_supplier_id', 'name', 'address', 'logo_id','fav_count']);
    }
}
