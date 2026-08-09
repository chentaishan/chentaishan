<?php

namespace app\api\model\supplier;

use app\common\model\supplier\Supplier as SupplierModel;
use app\common\model\supplier\User as SupplierUserModel;
use app\api\model\user\Favorite as FavoriteModel;
use app\api\model\product\Product as ProductModel;

/**
 * 供应商模型类
 */
class Supplier extends SupplierModel
{

    public function getUserName($user_name)
    {
        return $this->where('user_name', '=', $user_name)->count();
    }

    //获取店铺信息
    public function getDetail($data, $user)
    {
        $detail = $this->where(['shop_supplier_id' => $data['shop_supplier_id']])
            ->where('is_show', '=', 1)
            ->field("status,name as store_name,shop_supplier_id,logo_id,category_id,server_score,fav_count,user_id,product_sales,back_image,description,address,business_id,latitude,longitude,is_show,create_time")
            ->with(['logo', 'category', 'business'])
            ->find();
        if ($detail) {
            $detail['logos'] = isset($detail['logo']['file_path']) ? $detail['logo']['file_path'] : '';
            $detail['business_image'] = isset($detail['business']['file_path']) ? $detail['business']['file_path'] : '';
            $detail['category_name'] = $detail['category']['name'];
            unset($detail['logo']);
            unset($detail['category']);
            $detail['isfollow'] = 0;
            $detail['supplier_user_id'] = (new SupplierUserModel())->where('shop_supplier_id', '=', $detail['shop_supplier_id'])->value('supplier_user_id');
            if ($user) {
                $detail['isfollow'] = (new FavoriteModel)
                    ->where('pid', '=', $data['shop_supplier_id'])
                    ->where('user_id', '=', $user['user_id'])
                    ->where('type', '=', 10)
                    ->count();
            }
        }
        return $detail;
    }

    //获取微店账号信息
    public function getAccount($shop_supplier_id, $field = "*")
    {
        $detail = $this->where(['shop_supplier_id' => $shop_supplier_id])->field("$field")->find();
        return $detail;
    }

    //店铺列表
    public function supplierList($param)
    {
        // 排序规则
        $sort = [];
        if ($param['sortType'] === 'all') {
            $sort = ['s.create_time' => 'desc'];
        } else if ($param['sortType'] === 'sales') {
            $sort = ['product_sales' => 'desc'];
        } else if ($param['sortType'] === 'score') {
            $sort = ['server_score' => 'desc'];
        }

        $model = $this;
        if (isset($param['name']) && $param['name']) {
            $model = $model->where('name', 'like', '%' . $param['name'] . '%');
        }
        // 查询列表数据
        $list = $model->alias('s')->with(['logo', 'category'])
            ->where('s.is_delete', '=', '0')
            ->where('s.is_recycle', '=', 0)
            ->where('s.status', '=', 0)
            ->where('s.is_show', '=', 1)
            ->field("s.shop_supplier_id,s.name,s.fav_count,logo_id,category_id,server_score,product_sales,s.latitude,s.longitude,s.is_show")
            ->order($sort)
            ->paginate($param);
        $product_model = new ProductModel();
        foreach ($list as $key => &$v) {
            $productList = $product_model->with(['image.file'])
                ->where([
                    'shop_supplier_id' => $v['shop_supplier_id'],
                    'product_status' => 10,
                    'audit_status' => 10,
                    'is_delete' => 0
                ])
                ->order('product_sort asc,product_id desc')
                ->limit(3)
                ->field('product_id,product_price,product_name,sales_initial,sales_actual,line_price')
                ->select();
            $v['productList'] = $productList;
            $v['logos'] = $v['logo'] ? $v['logo']['file_path'] : '';
            $v['category_name'] = isset($v['category']) ? $v['category']['name'] : '';
            unset($v['logo']);
            unset($v['category']);
        }
        return $list;
    }

    /**
     * H5 可展示商户列表（仅返回上架可见商户）
     */
    public function visibleList($param)
    {
        $sort = ['s.create_time' => 'desc'];
        if (($param['sortType'] ?? '') === 'sales') {
            $sort = ['s.product_sales' => 'desc'];
        } elseif (($param['sortType'] ?? '') === 'score') {
            $sort = ['s.server_score' => 'desc'];
        }

        $model = $this->alias('s')->with(['logo', 'category'])
            ->where('s.is_delete', '=', 0)
            ->where('s.is_recycle', '=', 0)
            ->where('s.status', '=', 0)
            ->where('s.is_show', '=', 1);

        if (!empty($param['name'])) {
            $model = $model->where('s.name', 'like', '%' . trim((string)$param['name']) . '%');
        }

        $list = $model->field('s.shop_supplier_id,s.name,s.logo_id,s.category_id,s.server_score,s.product_sales,s.address,s.latitude,s.longitude,s.is_show')
            ->order($sort)
            ->paginate($param);

        foreach ($list as &$item) {
            $item['logo_path'] = isset($item['logo']['file_path']) ? $item['logo']['file_path'] : '';
            $item['category_name'] = isset($item['category']['name']) ? $item['category']['name'] : '';
            unset($item['logo'], $item['category']);
        }

        return $list;
    }

    //查询用户申请供应商状态
    public static function getStatus($user)
    {
        $apply = (new Apply())->where('user_id', '=', $user['user_id'])
            ->where('is_delete', '=', 0)
            ->order('supplier_apply_id desc')
            ->find();
        $supplier = (new static())->where('user_id', '=', $user['user_id'])
            ->where('is_delete', '=', 0)
            ->count();
        $status = 0;
        if ($user['user_type'] == 2) {
            if ($supplier) {
                $status = 2;
            } else {
                $status = 3;
            }
        } else {
            if ($apply) {
                if ($apply['status'] == 0 || $apply['status'] == 2) {
                    $status = 1;
                }
            }
        }
        return $status;
    }
}
