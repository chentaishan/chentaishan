<?php

namespace app\common\library\sms;

/**
 * 短信模板文案：后台 engine 中已填则优先；短信宝且后台为空时使用 config/sms.php
 * （阿里云/腾讯云等为模板 ID，不在此回退，避免把中文当成模板编码提交）
 */
class TemplateConfig
{
    /**
     * 若 config/sms.php 中 default_engine 非空，则覆盖数据库中的默认引擎名
     */
    public static function withDefaultEngine(array $smsConfig): array
    {
        $override = config('sms.default_engine');
        if (is_string($override) && $override !== '') {
            $smsConfig['default'] = $override;
        }
        return $smsConfig;
    }

    public static function resolve(string $key, array $engineRow, string $engineName): string
    {
        if (isset($engineRow[$key])) {
            $v = $engineRow[$key];
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        if (strtolower($engineName) !== 'smsbao') {
            return '';
        }
        $templates = config('sms.templates', []);
        return isset($templates[$key]) ? (string)$templates[$key] : '';
    }
}
