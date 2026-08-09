<?php

namespace app\shop\controller\plus\live;

use app\shop\controller\Controller;
use app\shop\model\settings\Setting as SettingModel;

/**
 * 直播设置
 */
class Setting extends Controller
{
    /**
     *获取直播设置
     */
    public function getSetting()
    {
        $vars['values'] = SettingModel::getItem('live');
        // 声网录制存储
        $storage = $this->getStorage();
        return $this->renderSuccess('', compact('vars', 'storage'));
    }

    /**
     * 直播设置
     */
    public function index()
    {
        if($this->request->isGet()){
            return $this->getSetting();
        }
        $model = new SettingModel;
        $data = $this->request->param();
        if ($model->edit('live', $data)) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError('操作失败');
    }

    /**
     * 声网录制存储
     */
    private function getStorage(){
        $storage = [
            'qiniu' => [
                'vendor' => '0',
                'name' => '七牛云',
                'region' => [
                    '华东' => '0',
                    '华北' => '1',
                    '华南' => '2',
                    '北美' => '3',
                    '东南亚' => '4',
                ]
            ],
            'aliyun' => [
                'vendor' => '2',
                'name' => '阿里云',
                'region' => [
                    '杭州' => '0',
                    '上海' => '1',
                    '青岛' => '2',
                    '北京' => '3',
                    '张家界' => '4',
                    '呼和浩特' => '5',
                    '深圳' => '6',
                    '香港' => '7',
                ]
            ],
            'qcloud' => [
                'vendor' => '3',
                'name' => '腾讯云',
                'region' => [
                    '北京' => '1',
                    '上海' => '2',
                    '广州' => '3',
                    '成都' => '4',
                    '重庆' => '5',
                    '深圳金融' => '6',
                    '上海金融' => '7',
                    '北京金融' => '8',
                    '香港' => '9',
                ]
            ]
        ];
        return $storage;
    }
}