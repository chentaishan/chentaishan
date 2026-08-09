<?php

namespace app\api\controller\plus\advance;

use app\api\controller\Controller;
use app\api\model\plus\advance\Product as ProductModel;
use app\common\service\product\BaseProductService;
use app\api\model\settings\Setting as SettingModel;

/**
 * 预售商品控制器
 */
class Product extends Controller
{
    /**
     *预售商品列表
     */
    public function index()
    {
        $model = new ProductModel();
        $list = $model->getList($this->request->param());
        // 预售设置
        $setting = SettingModel::getItem('advance');
        return $this->renderSuccess('', compact('list', 'setting'));
    }

}