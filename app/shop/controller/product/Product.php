<?php

namespace app\shop\controller\product;

use app\api\controller\user\Order;
use app\common\model\order\CloudBillRecord;
use app\common\model\order\CloudCardApply;
use app\common\model\order\CloudInformation;
use app\common\model\order\CloudPayApply;
use app\common\model\order\CloudPayConfig;
use app\common\model\order\CloudRatio;
use app\common\model\order\CloudReserveRelation;
use app\common\model\order\CloudUpRatio;
use app\common\model\user\User;
use app\shop\model\product\Product as ProductModel;
use app\shop\model\product\Category as CategoryModel;
use app\shop\model\supplier\Supplier as SupplierModel;
use app\shop\service\ProductService;
use app\shop\controller\Controller;
use think\facade\Db;

/**
 * 商品管理控制器
 */
class Product extends Controller
{
    /**
     * 商品列表(全部)
     */
    public function index()
    {
        // 获取全部商品列表
        $model = new ProductModel;
        $list = $model->getList(array_merge(['status' => -1], $this->postData()));
        // 商品分类
        $category = CategoryModel::getCacheTree();
        // 数量
        $product_count = [
            'sell' => $model->getCount('sell',$this->postData()),
            'recovery' => $model->getCount('recovery',$this->postData()),
            'lower' => $model->getCount('lower',$this->postData()),
            'audit' => $model->getCount('audit',$this->postData()),
            'no_audit' => $model->getCount('no_audit',$this->postData())
        ];
        //商户列表
        $supplierList = SupplierModel::getAll();
        return $this->renderSuccess('', compact('list', 'category', 'product_count', 'supplierList'));
    }

    /**
     * 商品列表(在售)
     */
    public function lists()
    {
        // 获取全部商品列表
        $model = new ProductModel;
        $list = $model->getLists($this->postData());
        // 商品分类
        $catgory = CategoryModel::getCacheTree();
        return $this->renderSuccess('', compact('list', 'catgory'));
    }

    /**
     * 添加商品
     */
    public function add($scene = 'add')
    {
        // get请求
        if ($this->request->isGet()) {
            return $this->getBaseData();
        }
        //post请求
        $data = json_decode($this->postData()['params'], true);
        if ($scene == 'copy') {
            unset($data['create_time']);
            unset($data['sku']['product_sku_id']);
            unset($data['sku']['product_id']);
            unset($data['product_sku']['product_sku_id']);
            unset($data['product_sku']['product_id']);
            if ($data['spec_type'] == 20) {
                foreach ($data['spec_many']['spec_list'] as &$spec) {
                    $spec['product_sku_id'] = 0;
                }
            }
            //初始化销量等数据
            $data['sales_initial'] = 0;
        }

        $model = new ProductModel;
        if (isset($data['product_id'])) {
            $data['product_id'] = 0;
        }

        if ($model->add($data)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 获取基础数据
     */
    public function getBaseData()
    {
        return $this->renderSuccess('', array_merge(ProductService::getEditData(null, 'add'), []));
    }

    /**
     * 获取编辑数据
     */
    public function getEditData($product_id, $scene = 'edit')
    {
        $model = ProductModel::detail($product_id);
        return $this->renderSuccess('', array_merge(ProductService::getEditData($model, $scene), compact('model')));
    }

    /**
     * 商品编辑
     */
    public function edit($product_id, $scene = 'edit')
    {
        if ($this->request->isGet()) {
            $model = ProductModel::detail($product_id);
            return $this->renderSuccess('', array_merge(ProductService::getEditData($model, $scene), compact('model')));
        }
        if ($scene == 'copy') {
            return $this->add($scene);
        }
        // 商品详情
        $model = ProductModel::detail($product_id);
        // 更新记录
        if ($model->edit(json_decode($this->postData()['params'], true))) {
            return $this->renderSuccess('更新成功');
        }
        return $this->renderError($model->getError() ?: '更新失败');
    }

    /**
     * 修改商品状态
     */
    public function state($product_id, $state)
    {
        // 商品详情
        $model = ProductModel::detail($product_id);
        if (!$model->setStatus($state)) {
            return $this->renderError('操作失败');
        }
        return $this->renderSuccess('操作成功');
    }

    /**
     * 强制下架、再上架
     */
    public function audit($product_id, $state)
    {
        // 商品详情
        $model = ProductModel::detail($product_id);
        if (!$model->setAudit($state)) {
            return $this->renderError('操作失败');
        }
        return $this->renderSuccess('操作成功');
    }

    /**
     * 删除商品
     */
    public function delete($product_id)
    {
        // 商品详情
        $model = ProductModel::detail($product_id);
        if (!$model->setDelete()) {
            return $this->renderError($model->getError() ?: '删除失败');
        }
        return $this->renderSuccess('删除成功');
    }



    //充值配置列表
    public function payConfigList()
    {
        $data = CloudPayConfig::order('pay_config_id asc')->limit(1)->find();

        return $this->renderSuccess('', $data);
    }

    //充值配置修改
    public function payConfigUp()
    {
        $param = $this->request->post();
        if (!isset($param['pay_config_id']) || empty($param['pay_config_id'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['name'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['account'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['bank_name'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['qr_code'])) {
            return $this->renderError('缺少参数');
        }

        CloudPayConfig::where(['pay_config_id' => $param['pay_config_id']])->update([
            'name' => $param['name'],
            'account' => $param['account'],
            'bank_name' => $param['bank_name'],
            'price' => $param['price'],
            'qr_code' => $param['qr_code'],
        ]);

        return $this->renderSuccess('修改成功');
    }

    //充值申请列表
    public function payApplyList()
    {
        $param = $this->request->get();
        if (!isset($param['status'])) {
            return $this->renderError('缺少参数');
        }

        $where = [];
        if (isset($param['create_time'][0]) && !empty($param['create_time'][0]) && isset($param['create_time'][1]) && !empty($param['create_time'][1])) {
            // 添加时间范围条件
            $where[] = ['c.create_time', 'between', [$param['create_time'][0], date('Y-m-d 23:59:59', strtotime($param['create_time'][1]))]];
        }
        if (!empty($param['nickName'])) {
            $where[] = ['c.nickName', 'like', '%' . $param['nickName'] . '%'];
        }
        if (!empty($param['mobile'])) {
            $where[] = ['u.mobile', 'like', '%' . $param['mobile'] . '%'];
        }
        if ($param['status'] > 0) {
            $where[] = ['c.status', 'like', '%' . $param['status'] . '%'];
        }

        $list = CloudPayApply::alias('c')
            ->join('user u', 'u.user_id = c.user_id', 'left')
            ->order('c.pay_apply_id desc')
            ->where($where)
            ->field('c.*,u.mobile')
            ->paginate($param)
            ->each(function ($item) {
                $item['image'] = explode(',', $item['image']);
                return $item;
            });
        return $this->renderSuccess('', compact('list'));
    }

    //审核充值申请
    public function payApplyUp()
    {
        $param = $this->request->post();
        if (!isset($param['pay_apply_id']) || empty($param['pay_apply_id'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['status']) || empty($param['status'])) {
            return $this->renderError('缺少参数');
        }

        $apply = CloudPayApply::where(['pay_apply_id' => $param['pay_apply_id']])->where('status', '=', 1)->find();
        if (empty($apply)) {
            return $this->renderError('申请不存在');
        }

        if ($param['status'] == 3) {
            CloudPayApply::where(['pay_apply_id' => $param['pay_apply_id']])->update([
                'status' => $param['status'],
                'content' => $param['content'],
                'check_time' => date('Y-m-d H:i:s', time()),
                'check_user' => $this->store['user']['user_name'],
            ]);
            Order::billAdd($apply['user_id'], 'inc', 'balance', 1, 0, '驳回:' . $param['content']);
        } else {
            CloudPayApply::where(['pay_apply_id' => $param['pay_apply_id']])->update([
                'status' => 2,
                'content' => $param['content'],
                'check_time' => date('Y-m-d H:i:s', time()),
                'check_user' => $this->store['user']['user_name'],
            ]);
            //添加数据  本人获得
            Order::billAdd($apply['user_id'], 'inc', 'balance', 1, $apply['price'], '充值');

//            $user = User::where(['user_id' => $apply['user_id']])->find();
//            if($user['referee_id'] != 0){
//                $pUser = User::where(['user_id' => $user['referee_id']])->find();
//                $ratioOne = CloudRatio::where(['ratio_id' => 1])->find();
//                //直推奖励
//                Order::billAdd($user['referee_id'], 'inc', 'balance', 1, $apply['price'] / 100 * $ratioOne['num'], '直推奖励来自' . $user['mobile']);
//
//                //间推奖励
//                if($pUser['referee_id'] != 0){
//                    $ratioTwo = CloudRatio::where(['ratio_id' => 2])->find();
//                    Order::billAdd($pUser['referee_id'], 'inc', 'balance', 1, $apply['price'] / 100 * $ratioTwo['num'], '间推奖励来自' . $user['mobile']);
//
//                }
//            }

        }

        return $this->renderSuccess('审核成功');
    }

    //获取全局比例配置
    public function getRatioInfo()
    {
        $list = CloudRatio::where('status',0)->select();

        return $this->renderSuccess('', $list);
    }

    //修改全局比例配置
    public function ratioUp()
    {
        $param = $this->request->post();
        if (!isset($param['ratio_id']) || empty($param['ratio_id'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['num']) || $param['num'] === '') {
            return $this->renderError('缺少参数');
        }
        $data = CloudRatio::where(['ratio_id' => $param['ratio_id']])->find();

        CloudRatio::where(['ratio_id' => $param['ratio_id']])->update([
            'num' => $param['num']
        ]);
//
//        CloudUpRatio::create([
//            'admin_id' => $this->store['user']['shop_user_id'],
//            'admin_name' => $this->store['user']['user_name'],
//            'ratio_name' => $data['name'],
//            'old_num' => $data['num'],
//            'new_num' => $param['num'],
//        ]);


        return $this->renderSuccess('修改成功');
    }

    /**
     * 批量修改全局比例配置
     */
    public function batchRatioUp()
    {
        $param = $this->request->post();
        if (!isset($param['ratios']) || !is_array($param['ratios']) || empty($param['ratios'])) {
            return $this->renderError('参数错误，需要 ratios 数组');
        }

        Db::startTrans();
        try {
            $successCount = 0;
            foreach ($param['ratios'] as $item) {
                if (!isset($item['ratio_id']) || !isset($item['num'])) {
                    continue;
                }

                $ratioId = intval($item['ratio_id']);
                $data = CloudRatio::where(['ratio_id' => $ratioId])->find();
                if (!$data) {
                    continue;
                }

                CloudRatio::where(['ratio_id' => $ratioId])->update([
                    'num' => $item['num'],
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
                $successCount++;
            }

            Db::commit();
            return $this->renderSuccess("批量修改成功，共更新 {$successCount} 条配置");
        } catch (\Exception $e) {
            Db::rollback();
            return $this->renderError('批量修改失败：' . $e->getMessage());
        }
    }
    
    
    //账单列表
    public function getBillList()
    {
        $param = $this->request->get();
        $where = [];
        if (isset($param['create_time'][0]) && !empty($param['create_time'][0]) && isset($param['create_time'][1]) && !empty($param['create_time'][1])) {
            // 添加时间范围条件
            $where[] = ['create_time', 'between', [$param['create_time'][0], date('Y-m-d 23:59:59', strtotime($param['create_time'][1]))]];
        }
        if (!empty($param['mobile'])) {
            $user = User::where('mobile', 'like', '%' . $param['mobile'] . '%')->where(['is_delete' => 0])->column('user_id');

            $where[] = ['user_id', 'in', $user];
        }

        if (!empty($param['content'])) {
            $where[] = ['profit', 'like', '%' . $param['content'] . '%'];
        }

        if (!empty($param['cur_type'])) {
            $where[] = ['cur_type', '=', $param['cur_type']];
        }

        $list = CloudBillRecord::where($where)
            ->order('bill_record_id desc')
            ->paginate($param)
            ->each(function ($item) {
                $user = User::where(['user_id' => $item['user_id']])->find();
                $item['nickName'] = $user['nickName'] ?? '';
                $item['mobile'] = $user['mobile'] ?? '';
                $item['ac_type_text'] = $item['ac_type'] == 1 ? '增加' : "减少";
                $item['cur_type_text'] = self::$billType[$item['cur_type']] ?? '';

                return $item;
            });
        return $this->renderSuccess('', compact('list'));
    }

    //获取分区映射
    public function getBillType()
    {
        $arr =  [
            [
                'cur_type' => 1,
                'content' => '余额',
            ],
            [
                'cur_type' => 2,
                'content' => '消费券',
            ],
            [
                'cur_type' => 3,
                'content' => '积分',
            ]
        ];

        return $this->renderSuccess('', $arr);
    }

    public static $billType = [
        1 => '余额',
        2 => '消费券',
        3 => '积分',
    ];


    //数据统计
    public function getAllData()
    {
        // 获取本月开始和结束的时间戳
        $startTime = strtotime(date('Y-m-01 00:00:00'));  // 本月第一天
        $endTime = strtotime(date('Y-m-t 23:59:59'));     // 本月最后一天

        $list = [];
        //总销售额
        $list['all_sell'] = \app\common\model\order\Order::where(['pay_status' => 20])
            ->sum('pay_price');
        //消费区总销售额
        $list['all_one_sell'] = \app\common\model\order\Order::where(['pay_status' => 20])
            ->where(['zone_type' => 1])
            ->sum('pay_price');
        //升级区总销售额
        $list['all_two_sell'] = \app\common\model\order\Order::where(['pay_status' => 20])
            ->where(['zone_type' => 2])
            ->sum('pay_price');

        //本月销售额
        $list['month_sell'] = \app\common\model\order\Order::where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->where(['pay_status' => 20])
            ->sum('pay_price');
        //本月消费区销售额
        $list['month_one_sell'] = \app\common\model\order\Order::where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->where(['zone_type' => 1])
            ->where(['pay_status' => 20])
            ->sum('pay_price');
        //本月升级区销售额
        $list['month_two_sell'] = \app\common\model\order\Order::where('create_time', '>=', $startTime)
            ->where('create_time', '<=', $endTime)
            ->where(['zone_type' => 2])
            ->where(['pay_status' => 20])
            ->sum('pay_price');

        //本月合伙商分红
        $orderPrice = $list['month_sell'] * 5 / 100;

        $ratioF = CloudRatio::where(['ratio_id' => 5])->find();
        $ratioFive = $ratioF ? $ratioF['num'] : 0;

        $list['month_bonus'] = $orderPrice * $ratioFive / 100;

        //本月代理分红
        $list['month_d_bonus'] = $list['month_sell'] * 20 / 100  * $ratioFive / 100;
        //上月合伙商分红
        $startDate = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $endDate = date('Y-m-t 23:59:59', strtotime('-1 month'));

        $list['last_bonus'] = CloudBillRecord::whereBetween('create_time', [$startDate, $endDate])
            ->where(['order_id' => -1])
            ->sum('price');

        //上月代理分红总额

        $list['last_d_bonus'] = CloudBillRecord::whereBetween('create_time', [$startDate, $endDate])
            ->where(['order_id' => -2])
            ->sum('price');


        return $this->renderSuccess('', $list);
    }



    //提现申请列表
    public function cardApplyList()
    {
        $param = $this->request->get();
        if (!isset($param['status'])) {
            $param['status'] = 0;
        }
        $where = [];
        if (isset($param['create_time'][0]) && !empty($param['create_time'][0]) && isset($param['create_time'][1]) && !empty($param['create_time'][1])) {
            // 添加时间范围条件
            $where[] = ['c.create_time', 'between', [$param['create_time'][0], date('Y-m-d 23:59:59', strtotime($param['create_time'][1]))]];
        }
        if (!empty($param['nickName'])) {
            $where[] = ['u.nickName', 'like', '%' . $param['nickName'] . '%'];
        }
        if (!empty($param['mobile'])) {
            $where[] = ['u.mobile', 'like', '%' . $param['mobile'] . '%'];
        }

        if ($param['status'] != 0) {
            $list = CloudCardApply::alias('c')
                ->join('user u', 'u.user_id = c.user_id', 'left')
                ->where(['c.status' => $param['status']])
                ->where($where)
                ->field('c.*,u.balance,u.nickName,u.mobile')
                ->order('c.card_apply_id desc')
                ->paginate($param)
                ->each(function ($item) {
                    $item['now_ticket'] = $item['balance'];
                    return $item;
                });


            return $this->renderSuccess('', compact('list'));
        }
        $list = CloudCardApply::alias('c')
            ->join('user u', 'u.user_id = c.user_id', 'left')
            ->where($where)
            ->field('c.*,u.balance,u.nickName,u.mobile')
            ->order('c.card_apply_id desc')
            ->paginate($param)
            ->each(function ($item) {
                $item['now_ticket'] = $item['balance'];
                return $item;
            });


        return $this->renderSuccess('', compact('list'));
    }

    //审核提现申请
    public function cardApplyUp()
    {
        $param = $this->request->post();
        if (!isset($param['card_apply_id']) || empty($param['card_apply_id'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['status']) || empty($param['status'])) {
            return $this->renderError('缺少参数');
        }

        $apply = CloudCardApply::where(['card_apply_id' => $param['card_apply_id']])->whereIn('status', [1,5])->find();
        if (empty($apply)) {
            return $this->renderError('申请不存在或已操作');
        }

        if ($param['status'] == 3) {
            CloudCardApply::where(['card_apply_id' => $param['card_apply_id']])->update([
                'status' => $param['status'],
                'content' => $param['content'],
                'check_time' => date('Y-m-d H:i:s', time()),
                'check_user' => $this->store['user']['user_name'],
            ]);
            $ratioF = CloudRatio::where(['ratio_id' => 6])->find();
            $ratioFive = $ratioF['num'];
            //减少数据 添加记录
            Order::billAdd($apply['user_id'], 'inc', 'balance', 1, $apply['num'] / (100-$ratioFive)*100, '兑金审核驳回');
            // Order::billAdd($apply['user_id'], 'inc', 'balance', 1, $apply['num'] * ($ratioFive / 100-$ratioFive), '兑金审核驳回手续费反还');
        } else if($param['status'] == 2) {
            CloudCardApply::where(['card_apply_id' => $param['card_apply_id']])->update([
                'status' => 2,
                'content' => $param['content'],
                'check_time' => date('Y-m-d H:i:s', time()),
                'check_user' => $this->store['user']['user_name'],
            ]);
            //减少数据 添加记录
            //Order::billAdd($apply['user_id'],'dec', 'balance', 5, $apply['num'], '提现');
        } else {
            CloudCardApply::where(['card_apply_id' => $param['card_apply_id']])->update([
                'status' => 5,
                'check_time' => date('Y-m-d H:i:s', time()),
                'check_user' => $this->store['user']['user_name'],
            ]);
        }

        return $this->renderSuccess('审核成功');
    }

    // 批量审核
    public function cardApplyUpBatch(){
        $param = $this->request->post();
        if (!isset($param['status']) || empty($param['status'])) {
            return $this->renderError('缺少参数');
        }

        $where = [];
        if (isset($param['create_time'][0]) && !empty($param['create_time'][0]) && isset($param['create_time'][1]) && !empty($param['create_time'][1])) {
            // 添加时间范围条件
            $where[] = ['create_time', 'between', [$param['create_time'][0], date('Y-m-d 23:59:59', strtotime($param['create_time'][1]))]];

            $applyList = CloudCardApply::where($where)->where('status', '=', 1)->select()->toArray();
        } else{

            return $this->renderError('请输入时间范围！');
        }


        if ($param['status'] == 3) {
            $data = [
                'status' => $param['status'],
                'content' => $param['content'],
                'check_time' => date('Y-m-d H:i:s', time()),
                'check_user' => $this->store['user']['user_name'],
            ];

            $ratioF = CloudRatio::where(['ratio_id' => 6])->find();
            $ratioFive = $ratioF['num'];
            foreach ($applyList as $apply) {
                //减少数据 添加记录
                Order::billAdd($apply['user_id'], 'inc', 'balance', 1, $apply['num'], '兑金审核驳回');
                Order::billAdd($apply['user_id'], 'inc', 'balance', 1, $apply['num'] * ($ratioFive / 100), '兑金审核驳回手续费反还');
            }

        } else {
            $data = [
                'status' => 2,
                'content' => $param['content'],
                'check_time' => date('Y-m-d H:i:s', time()),
                'check_user' => $this->store['user']['user_name'],
            ];
            $applyIds = array_column($applyList,'card_apply_id');
            CloudCardApply::where('card_apply_id' ,'in',$applyIds)->update($data);
            //减少数据 添加记录
            //Order::billAdd($apply['user_id'],'dec', 'balance', 5, $apply['num'], '提现');
        }

        return $this->renderSuccess('审核成功');

    }

    //资讯列表(多条, 分页)
    public function getInformation()
    {
        $list = (new CloudInformation())->getList($this->request->param(), $this->store['app']['app_id']);
        return $this->renderSuccess('操作成功', compact('list'));
    }

    //资讯详情
    public function getInformationInfo($information_id)
    {
        $detail = CloudInformation::detail($information_id, $this->store['app']['app_id']);
        if (!$detail) {
            return $this->renderError('资讯不存在');
        }
        return $this->renderSuccess('操作成功', ['detail' => $detail]);
    }

    //新增或修改资讯(带 information_id 为修改, 否则新增)
    public function UpInformation()
    {
        $param = $this->request->post();

        if (!isset($param['content']) || empty($param['content'])) {
            return $this->renderError('缺少内容');
        }

        if (!empty($param['information_id'])) {
            $model = CloudInformation::detail($param['information_id'], $this->store['app']['app_id']);
            if (!$model) {
                return $this->renderError('资讯不存在');
            }
            if ($model->edit($param)) {
                return $this->renderSuccess('操作成功');
            }
            return $this->renderError($model->getError() ?: '操作失败');
        }

        $model = new CloudInformation();
        if ($model->add($param, $this->store['app']['app_id'])) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    //删除资讯
    public function delInformation($information_id)
    {
        $model = CloudInformation::detail($information_id, $this->store['app']['app_id']);
        if (!$model) {
            return $this->renderError('资讯不存在');
        }
        if ($model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    //设置资讯显示状态
    public function setInformationStatus($information_id, $status)
    {
        $model = CloudInformation::detail($information_id, $this->store['app']['app_id']);
        if (!$model) {
            return $this->renderError('资讯不存在');
        }
        if ($model->setStatus($status)) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    //设置资讯排序
    public function setInformationSort($information_id, $sort)
    {
        $model = CloudInformation::detail($information_id, $this->store['app']['app_id']);
        if (!$model) {
            return $this->renderError('资讯不存在');
        }
        if ($model->setSort($sort)) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

}
