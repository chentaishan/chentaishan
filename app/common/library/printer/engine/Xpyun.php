<?php

namespace app\common\library\printer\engine;

use app\common\library\printer\party\XpHttpClient;

/**
 * 芯烨小票打印机API引擎
 */
class Xpyun extends Basics
{
    // 接口url
    const url = 'https://open.xpyun.net/api/openapi/xprinter/print';

    /**
     * 执行订单打印
     */
    public function printTicket($content)
    {
        // 构建请求参数
        $request = $this->getParams($content);
        $jsonRequest = json_encode($request);
        $client = new XpHttpClient();

        $returnContent = $client->post(self::url, $jsonRequest);
        $result = json_decode($returnContent);
        // 返回状态
        if ($result->code != 0) {
            $this->error = $result->msg;
            return false;
        }
        return true;
    }

    /**
     * 构建Api请求参数
     */
    private function getParams($content)
    {
        $config = json_decode($this->config, true);
        $time = time();
        return [
            'user' => $config['USER'],
            'timestamp' => $time,
            'sign' => sha1($config['USER'] . $config['UKEY'] . $time),
            'debug' => 0,//1返回非json格式的数据，仅测试时候使用
            'sn' => $config['SN'],
            'content' => $content,
            'copies' => $this->times,    // 打印次数
            'mode' => 0,
            'voice' => 2,
        ];
    }

}