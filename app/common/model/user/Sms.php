<?php


namespace app\common\model\user;

use app\common\library\sms\Driver as SmsDriver;
use app\common\library\sms\TemplateConfig;
use app\common\model\BaseModel;
use app\common\model\settings\Setting as SettingModel;

/**
 * 短信模型
 */
class Sms extends BaseModel
{
    protected $pk = 'sms_id';
    protected $name = 'sms';

    /**
     * 短信发送
     * $sence 场景，login：登录 apply：供应商申请 sms：手机号验证码登录
     */
    public function send($mobile, $sence = 'login')
    {
        if (empty($mobile)) {
            $this->error = '手机号码不能为空';
            return false;
        }
        // H5/APP 验证码场景受商城「H5注册是否开启短信验证」控制
        if (in_array($sence, ['login', 'register', 'sms'], true)) {
            if (!$this->isH5SmsOpen()) {
                $this->error = '未开启短信验证功能';
                return false;
            }
        } elseif ($sence === 'apply') {
            // 供应商入驻验证码受「是否开启短信」控制
            if (!$this->isSupplierSmsOpen()) {
                $this->error = '未开启短信验证功能';
                return false;
            }
        }
        $smsConfig = TemplateConfig::withDefaultEngine(SettingModel::getItem('sms', self::$app_id));
        $engineName = $smsConfig['default'] ?? '';
        $template_code = $smsConfig['engine'][$engineName] ?? [];
        $send_template = '';
        if ($sence == 'login') {
            $send_template = TemplateConfig::resolve('login_template', $template_code, $engineName);
            if ($send_template === '') {
                $this->error = '短信登录未开启';
                return false;
            }
        } else if ($sence == 'apply') {
            $send_template = TemplateConfig::resolve('apply_template', $template_code, $engineName);
            if ($send_template === '') {
                $this->error = '短信模板未配置';
                return false;
            }
        } else if ($sence == 'register') {
            $send_template = TemplateConfig::resolve('login_template', $template_code, $engineName);
            if ($send_template === '') {
                $this->error = '短信登录未开启';
                return false;
            }
            //判断是否已经注册
            $user = (new User)->where('mobile', '=', $mobile)
                ->where('reg_source', 'in', ['h5', 'app'])
                ->where('is_delete', '=', 0)
                ->find();
            if ($user) {
                $this->error = '手机号码已存在';
                return false;
            }
        } else if ($sence == 'sms') {
            $send_template = TemplateConfig::resolve('login_template', $template_code, $engineName);
            if ($send_template === '') {
                $this->error = '短信登录未开启';
                return false;
            }
        }
        $code = str_pad(mt_rand(100000, 999999), 6, "0", STR_PAD_BOTH);
        $SmsDriver = new SmsDriver($smsConfig);
        $send_data = [
            'code' => $code
        ];
        //短信模板
        $result = $SmsDriver->sendSms($mobile, $send_template, $send_data);
        if ($result == 'OK') {
            $this->save([
                'mobile' => $mobile,
                'code' => $code,
                'sence' => $sence,
                'app_id' => self::$app_id
            ]);
            return true;
        }
        $this->error = $result;
        return false;
    }

    /**
     * 短信发送
     */
    public function sendTemplate($mobile, $template_code)
    {
        if (empty($mobile)) {
            $this->error = '手机号码不能为空';
            return false;
        }
        $smsConfig = TemplateConfig::withDefaultEngine(SettingModel::getItem('sms', self::$app_id));
        $engineName = $smsConfig['default'] ?? '';
        $engineRow = $smsConfig['engine'][$engineName] ?? [];
        $templateStr = TemplateConfig::resolve($template_code, $engineRow, $engineName);
        if ($templateStr === '') {
            $this->error = '短信登录未开启';
            return false;
        }
        $SmsDriver = new SmsDriver($smsConfig);
        $send_data = [
            'code' => '112'
        ];
        //短信模板
        $flag = $SmsDriver->sendSms($mobile, $templateStr, $send_data);
        return $flag;
    }

    /**
     * 商城设置：H5/APP 是否开启短信验证
     */
    private function isH5SmsOpen(): bool
    {
        $setting = SettingModel::getItem('store', self::$app_id);
        return $this->isSettingEnabled($setting['h5_sms_open'] ?? false);
    }

    /**
     * 商城设置：供应商入驻等是否开启短信验证
     */
    private function isSupplierSmsOpen(): bool
    {
        $setting = SettingModel::getItem('store', self::$app_id);
        return $this->isSettingEnabled($setting['sms_open'] ?? '0');
    }

    /**
     * 兼容 bool / 0|1 / '0'|'1' / 'true'|'false'
     */
    private function isSettingEnabled($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        if ($value === false || $value === 0 || $value === null || $value === '') {
            return false;
        }
        $str = strtolower(trim((string)$value));
        return in_array($str, ['1', 'true', 'yes', 'on'], true);
    }
}
