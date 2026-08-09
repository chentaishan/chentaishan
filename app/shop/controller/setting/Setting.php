<?php

namespace app\shop\controller\setting;

use think\Cache;
use app\common\model\settings\Setting as SettingModel;
use app\common\enum\settings\SettingEnum;
use think\facade\Db;


/**
 * 系统设置模型
 */
class Setting extends SettingModel
{
    /**
     * 更新系统设置
     */
    public function edit($key, $values)
    {
        $model = self::detail($key) ?: $this;
        // 数据验证
        if (!$this->validValues($key, $values)) {
            return false;
        }
        // 删除系统设置缓存
        Cache::delete('setting_' . self::$app_id);
        return $model->save([
                'key' => $key,
                'describe' => SettingEnum::data()[$key]['describe'],
                'values' => $values,
                'app_id' => self::$app_id,
            ]) !== false;
    }

    /**
     * 数据验证
     */
    private function validValues($key, $values)
    {
        $callback = [
            'store' => function ($values) {
                return $this->validStore($values);
            },
            'printer' => function ($values) {
                return $this->validPrinter($values);
            },
        ];
        // 验证商城设置
        return isset($callback[$key]) ? $callback[$key]($values) : true;
    }

    /**
     * 验证商城设置
     */
    private function validStore($values)
    {
        if (!isset($values['delivery_type']) || empty($values['delivery_type'])) {
            $this->error = '配送方式至少选择一个';
            return false;
        }
        return true;
    }

    /**
     * 验证小票打印机设置
     */
    private function validPrinter($values)
    {
        if ($values['is_open'] == false) {
            return true;
        }
        if (!$values['printer_id']) {
            $this->error = '请选择订单打印机';
            return false;
        }
        if (empty($values['order_status'])) {
            $this->error = '请选择订单打印方式';
            return false;
        }
        return true;
    }

    //设置客服图片
    public function setKefuImg($img_url){
        // 验证参数
        if (!isset($img_url) || empty($img_url)) {
            $this->error = '客服图片地址不能为空';
            return false;
        }
        //去除反斜杠
        $img_url = str_replace('\\', '', $img_url);
        // 构建JSON格式数据
        $values = [
            'img_url' => $img_url
        ];

        $values = json_encode($values);
        $bool = Db::name('setting')->where('key', 'kefu')->update(['values' => $values]);
        // 调用edit方法保存设置
        return json( ['code'=>1,'msg'=>'更新成功']);
    }
}
