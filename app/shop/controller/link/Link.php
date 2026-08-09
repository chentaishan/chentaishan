<?php

namespace app\shop\controller\link;

use app\shop\controller\Controller;
use app\shop\model\plus\assemble\Active;
use app\shop\model\plus\seckill\Active as ActiveModel;
use app\shop\model\page\Page as PageModel;
use app\shop\model\plus\table\Table as TableModel;

/**
 * Class Link
 * @package app\shop\controller\link
 * 超链接控制器
 */
class Link extends Controller
{
    /**
     *获取数据
     */
    public function index($activeName)
    {
        // 万能表单
        $list = (new TableModel())->getAll();
        $tableList = [];
        foreach ($list as $item) {
            $tableList[] = [
                'id' => $item['table_id'],
                'url' => 'pagesPlus/table/table?table_id=' . $item['table_id'],
                'name' => $item['name'],
                'type' => '表单'
            ];
        }
        return $this->renderSuccess('', compact('tableList'));
    }

    /**
     * 获取自定义页面
     */
    public function getPageList()
    {
        $model = new PageModel;
        $list = $model->getLists();
        return $this->renderSuccess('', compact('list'));
    }
}
