<?php

namespace app\supplier\controller\activity;

use app\supplier\controller\Controller;
use app\supplier\model\plus\advance\Product as AdvanceProductModel;
use app\common\model\product\Product as ProductModel;
use app\supplier\service\ProductService;

/**
 * 活动控制器
 */
class Advance extends Controller
{
    /**
     * 新增预售商品
     */
    public function index()
    {
        $model = new AdvanceProductModel();
        $list = $model->getList($this->postData(), $this->getSupplierId());
        //获取排除id
        $exclude_ids = $model->getExcludeIds($this->getSupplierId());
        $exclude_ids = array_column($exclude_ids, 'product_id');
        return $this->renderSuccess('', compact('list', 'exclude_ids'));
    }

    /**
     *添加商品
     */
    public function add($product_id)
    {
        if ($this->request->isGet()) {
            $model = ProductModel::detail($product_id);
            $specData = ProductService::getSpecData($model);
            $specList = $this->transSpecData($specData);
            return $this->renderSuccess('', compact('model', 'specList'));
        }
        $model = new AdvanceProductModel();
        if ($model->checkProduct($product_id)) {
            return $this->renderError('商品已经存在');
        }
        if ($model->saveProduct($this->postData(), $this->getSupplierId())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 组装前端用的数据
     */
    private function transSpecData($specData)
    {
        if (!$specData) {
            return [];
        }
        $specList = [];
        foreach ($specData['spec_list'] as $spec) {
            $specIds = explode('_', $spec['spec_sku_id']);
            $spec['spec_name'] = '';
            foreach ($specIds as $specId) {
                $spec['spec_name'] .= $this->searchSpecItem($specData['spec_attr'], $specId) . ';';
            }
            array_push($specList, $spec);
        }
        return $specList;
    }

    /**
     * 规格值
     */
    private function searchSpecItem($spec_attr, $item_id)
    {
        $specValue = '';
        foreach ($spec_attr as $attr) {
            foreach ($attr['spec_items'] as $item) {
                if ($item['item_id'] == $item_id) {
                    $specValue = $attr['group_name'] . ',' . $item['spec_value'];
                    break 2;
                }
            }
        }
        return $specValue;
    }

    /**
     *修改商品
     */
    public function edit($advance_product_id)
    {
        $model = AdvanceProductModel::detail($advance_product_id, ['product.image.file', 'sku']);
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }

        if ($model->saveProduct($this->postData(), $this->getSupplierId())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     *删除商品
     */
    public function delete($id)
    {
        $model = new AdvanceProductModel();
        if ($model->del($id)) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError('删除失败');
    }
}