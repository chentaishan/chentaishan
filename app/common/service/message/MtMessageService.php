<?php

namespace app\common\service\message;

use app\common\library\easywechat\AppMp;

/**
 * 公众号模板消息通知服务
 */
class MtMessageService
{
    /**
     * 订单支付成功后通知
     */
    public static function send($data, $mp_template, $touser, $app_id)
    {
        try {
            $data['title'] = '';
            $data['remark'] = '';
            $mp_template = json_decode($mp_template, true);

            $var_data = $mp_template['var_data'];
            $send_data = [];
            foreach ($var_data as $key => $value) {
                if (isset($data[$key])) {
                    if ($key == "title" || $key == "remark") {
                        $send_data[$value['field_name']]['value'] = $value['filed_value'];
                    } else {
                        $send_data[$value['field_name']]['value'] = $data[$key];
                    }
                } else {
                    $send_data[$key]['value'] = $value['filed_value'];
                }
            }
            foreach ($send_data as $key => $value) {
                if (mb_strlen($value['value']) > 20) {
                    $send_data[$key]['value'] = mb_substr($value['value'], 0, 20);
                }
            }
            $app = AppMp::getApp($app_id);
            $api = $app->getClient();
            $accessToken = $app->getAccessToken(); // 使用easywechat自带的方法,获取访问令牌
            $token = $accessToken->getToken(); // string
            $template = [
                'touser' => $touser,
                'template_id' => $mp_template['template_id'],
                'url' => '',
                'data' => $send_data,
            ];
            $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $token;
            $result = $api->postJson($url, $template);
            $result = $result->toArray(false);
            log_write($result);
        } catch (\Exception $e) {
            log_write('公众号消息发送失败');
            log_write($e->getMessage());
        }
    }
}