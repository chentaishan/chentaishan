<?php

namespace app\common\library\sms\engine;

/**
 * 短信宝 https://www.smsbao.com/ （HTTP GET 接口）
 * 配置项与后台「短信」表单字段对齐：AccessKeyId=短信宝账号，AccessKeySecret=登录密码（明文，请求时自动 md5）
 */
class Smsbao extends Server
{
    private $config;

    private static $statusMap = [
        '0' => '短信发送成功',
        '-1' => '参数不全',
        '-2' => '服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！',
        '30' => '密码错误',
        '40' => '账号不存在',
        '41' => '余额不足',
        '42' => '帐户已过期',
        '43' => 'IP地址限制',
        '50' => '内容含有敏感词',
    ];

    public function __construct($config)
    {
        $file = config('sms.smsbao', []);
        if (is_array($file)) {
            foreach (['AccessKeyId', 'AccessKeySecret', 'username', 'password', 'api_url'] as $k) {
                $cur = $config[$k] ?? '';
                if (($cur === '' || $cur === null) && isset($file[$k]) && $file[$k] !== '' && $file[$k] !== null) {
                    $config[$k] = $file[$k];
                }
            }
        }
        $this->config = $config;
    }

    /**
     * @param string $mobile
     * @param string $template_code 完整短信正文模板，支持占位符 {code}、{$code}
     * @param array|string $templateParams 模板变量，如 ['code' => '123456']
     */
    public function sendSms($mobile, $template_code, $templateParams)
    {
        if ($template_code === '' || $template_code === null) {
            $this->error = '短信模板未配置';
            return $this->error;
        }

        $username = $this->config['username'] ?? $this->config['AccessKeyId'] ?? '';
        $password = $this->config['password'] ?? $this->config['AccessKeySecret'] ?? '';
        if ($username === '') {
            $this->error = '短信宝账号未配置';
            return $this->error;
        }
        if ($password === '') {
            $this->error = '短信宝密码未配置';
            return $this->error;
        }

        if (!is_array($templateParams)) {
            $templateParams = [];
        }

        $content = $this->buildContent((string)$template_code, $templateParams);
        $base = $this->config['api_url'] ?? 'http://api.smsbao.com/';
        $base = rtrim($base, '/') . '/';

        $passMd5 = md5($password);
        $url = $base . 'sms?u=' . rawurlencode($username)
            . '&p=' . rawurlencode($passMd5)
            . '&m=' . rawurlencode((string)$mobile)
            . '&c=' . rawurlencode($content);

        $result = $this->httpGet($url);
        if ($result === false) {
            return $this->error;
        }
        $code = trim($result);
        if ($code === '0') {
            return 'OK';
        }
        $msg = self::$statusMap[$code] ?? ('发送失败(' . $code . ')');
        $this->error = $msg;
        return $msg;
    }

    private function buildContent(string $template, array $params): string
    {
        $content = $template;
        foreach ($params as $key => $val) {
            if (!is_scalar($val) && $val !== null) {
                continue;
            }
            $str = (string)$val;
            $content = str_replace(
                ['{' . $key . '}', '{{' . $key . '}}', '${' . $key . '}'],
                $str,
                $content
            );
        }
        return $content;
    }

    /**
     * @return string|false
     */
    private function httpGet(string $url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($body === false) {
                $this->error = $err ?: '短信接口请求失败';
                return false;
            }
            return (string)$body;
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 15]]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                $this->error = '无法请求短信接口，请开启 curl 或 allow_url_fopen';
                return false;
            }
            return (string)$body;
        }
        $this->error = '服务器未启用 curl 且禁止访问外网 URL';
        return false;
    }
}
