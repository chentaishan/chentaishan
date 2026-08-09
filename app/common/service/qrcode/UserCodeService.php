<?php

namespace app\common\service\qrcode;

use Endroid\QrCode\QrCode;
use Grafika\Color;
use Grafika\Grafika;
use app\common\model\user\User as UserModel;
use app\common\model\user\BalanceLog as BalanceLogModel;
// use Grafika\Grafika;

class UserCodeService extends Base
{
    private $user;
    private $user_id;

    private $source;

    private $pages = '';


    private static array $CHARS = ["Y","2","U","K","X","V","C","F","N","S","6","8","G","Z","Q","7","A","9","P","H","5","M","R","L","D","J","4","T","W","E","3","B"];

    private const CHARS_LENGTH = 32;
    private const SLAT = 3312427;
    private const PRIME1 = 3;
    private const PRIME2 = 11;

    public function __construct($user,$userId,$source){
        parent::__construct();
        $this->user_id = $userId;
        $this->user = $user;
        $this->source = $source;
    }

    // 生成邀请码
    public static function generateCode($id, $length = 6): string
    {
        // 对 ID 进行加密处理
        $id = $id * self::PRIME1 + self::SLAT;
        $b = [];
        $b[0] = $id;
        for ($i = 0; $i < $length - 1; $i++) {
            $b[$i + 1] = $b[$i] / self::CHARS_LENGTH;
            $b[$i] = (int) ($b[$i] + $i * $b[0]) % self::CHARS_LENGTH;
        }

        // 计算邀请码索引
        $tmp = 0;
        for ($i = 0; $i < $length - 2; $i++) {
            $tmp += $b[$i];
        }
        $b[$length - 1] = $tmp * self::PRIME1 % self::CHARS_LENGTH;

        // 混淆生成邀请码
        $codeIndexArray = [];
        for ($i = 0; $i < $length; $i++) {
            $codeIndexArray[$i] = $b[$i * self::PRIME2 % $length];
        }

        $buffer = '';
        foreach ($codeIndexArray as $index) {
            $buffer .= self::$CHARS[$index];
        }
        return $buffer;
    }

    // 解密邀请码获取原始 ID
    public static function decode($code): ?int
    {

        $length = strlen($code);

        // 将字符转换为对应数字
        $a = [];
        for ($i = 0; $i < $length; $i++) {
            $c = $code[$i];
            $index = self::findIndex($c);
            if ($index == -1) {
                return null;
            }
            $a[$i * self::PRIME2 % $length] = $index;
        }

        // 逆向计算出原始 ID
        $b = [];
        for ($i = $length - 2; $i >= 0; $i--) {
            $b[$i] = ($a[$i] - $a[0] * $i + self::CHARS_LENGTH * $i) % self::CHARS_LENGTH;
        }

        $res = 0;
        for ($i = $length - 2; $i >= 0; $i--) {
            $res += $b[$i];
            $res *= ($i > 0 ? self::CHARS_LENGTH : 1);
        }
        return (int) (($res - self::SLAT) / self::PRIME1);
    }

    // 查找字符在字符集中的位置
    public static function findIndex($c): int
    {
        foreach (self::$CHARS as $key => $char) {
            if ($char == $c) {
                return $key;
            }
        }
        return -1;
    }

    /**
     * @return mixed
     */
    public function getImage()
    {
        $code = self::generateCode($this->user_id);
        // 判断海报图文件存在则直接返回url
//        if (file_exists($this->getPosterPath())) {
//            return $this->getPosterUrl();
//        }
        // 小程序id
        $appId = $this->user['app_id'];
        // 商品海报背景图
        //$backdrop = __DIR__ . '/resource/room_bg.png';
        $backdrop = __DIR__ . '/resource/new_code_bg.png';
        //$backdrop = __DIR__ . '/resource/newbg1.png';
        //下载商品首图
//        if($this->user['avatarUrl'] != '' && !strstr($this->user['avatarUrl'],'avatarUrl')){
//            $avatarUrl = $this->saveTempImage($appId, $this->user['avatarUrl'], 'avatar');
//        }
//        else{
//            // dump(2);
//            // exit;
//            $avatarUrl = __DIR__ . '/resource/user.png';
//        }
        $qrcode = null;
        $this->source = 'h5';//业务需求先定死h5,后续可删掉此行
        if($this->source == 'wx'){
            // 小程序码参数
            $scene = "referee_id:" . ($this->user_id ?: '');
            // 下载小程序码
            $this->pages = 'pages/login/login';//'pages/login/login';
            $qrcode = $this->saveQrcode($appId, $scene, $this->pages);
        }else if($this->source == 'mp'){
            $scene = "gen_code:" . ($code ?: '');
            $qrcode = new QrCode(base_url().'index.php/api/user.usermp/login?app_id='.$this->user['app_id'].'&gen_code='.$code);
            // $qrcode = new QrCode(base_url().'h5/pages/login/mplogin?app_id='.$this->user['app_id'].'&referee_id='.$this->user_id);
            // $qrcode = new QrCode('http://121.41.0.35:10066/h5/pages/login/weblogin?app_id='.$this->user['app_id'].'&gen_code='.$code);
            $qrcode = $this->saveMpQrcode($qrcode, $appId, $scene, 'image_mp');
        }
        else if($this->source == 'h5'){
            $scene = "gen_code:" . ($code ?: '');
            $qrcode = new QrCode(base_url().'h5/pages/login/weblogin?app_id=10001&referee_id='.$this->user_id);
            // $qrcode = new QrCode('http://121.41.0.35:10066/h5/pages/login/weblogin?app_id='.$this->user['app_id'].'&gen_code='.$code);
            $qrcode = $this->saveMpQrcode($qrcode, $appId, $scene, 'image_mp');
        }

        return [
            'backdrop' =>  \base_url() . "temp/{$appId}/new_code_bg.png",
            'code'  =>  \base_url() . "/temp/{$appId}/image_mp/qrcode_" . md5($appId . $scene) . ".png"
        ];
        //直接返回二维码
        return \base_url() . "/temp/{$appId}/image_mp/qrcode_" . md5($appId . $scene) . ".png";
        // 拼接海报图
        return $this->savePoster($backdrop, $qrcode);
    }


    public function codeImage()
    {
        $code = self::generateCode($this->user_id);
        // 判断海报图文件存在则直接返回url
//        if (file_exists($this->getPosterPath())) {
//            return $this->getPosterUrl();
//        }
        // 小程序id
        $appId = $this->user['app_id'];
        // 商品海报背景图
        //$backdrop = __DIR__ . '/resource/room_bg.png';
        //$backdrop = __DIR__ . '/resource/newbg1.png';
        //下载商品首图
        // if($this->user['avatarUrl'] != '' && !strstr($this->user['avatarUrl'],'avatarUrl')){
        //     $avatarUrl = $this->saveTempImage($appId, $this->user['avatarUrl'], 'avatar');
        // }
        // else{
        // dump(2);
        // exit;
        // $avatarUrl = __DIR__ . '/resource/user.png';
        // }
        $qrcode = null;
        if($this->source == 'wx'){
            // 小程序码参数
            $scene = "referee_id:" . ($this->user_id ?: '');
            // 下载小程序码
            $this->pages = 'pages/login/login';
            $qrcode = $this->saveQrcodeWx($appId, $scene, $this->pages);
            //return $qrcode;
        }else if($this->source == 'mp' || $this->source == 'h5'){
            $scene = "gen_code:" . ($code ?: '');
            $qrcode = new QrCode(base_url().'index.php/api/user.usermp/login?app_id='.$this->user['app_id'].'&gen_code='.$code);
            // $qrcode = new QrCode(base_url().'h5/pages/login/mplogin?app_id='.$this->user['app_id'].'&referee_id='.$this->user_id);
            // $qrcode = new QrCode('http://121.41.0.35:10066/h5/pages/login/weblogin?app_id='.$this->user['app_id'].'&gen_code='.$code);
            $qrcode = $this->saveMpQrcodeNew($qrcode, $appId, $scene, 'image_mp');
            //return $qrcode;
        }
        else if($this->source == 'h5'){
            $scene = "gen_code:" . ($code ?: '');
            $qrcode = new QrCode(base_url().'h5/pages/login/weblogin?app_id=10001&gen_code?app_id='.$this->user['app_id'].'&gen_code='.$code);
            $qrcode = $this->saveQrcodeNew($qrcode, $appId, $scene, 'image_mp');

            // $qrcode = new QrCode('http://121.41.0.35:10066/h5/pages/login/weblogin?app_id='.$this->user['app_id'].'&gen_code='.$code);
            // $qrcode = $this->saveMpQrcode($qrcode, $appId, $scene, 'image_mp');
        }
        // 拼接海报图
        // return $this->savePoster($backdrop,$avatarUrl, $qrcode);
        return $qrcode;
    }

    private function savePoster($backdrop, $qrcode)
    {
        // 实例化图像编辑器
        $editor = Grafika::createEditor(['Gd']);
        // 字体文件路径
        $fontPath = public_path() . '/static/' . 'st-heiti-light.ttc';
        // 打开海报背景图
        $editor->open($backdropImage, $backdrop);
        // 2. 下载头像图片到内存
        //$avatarContent = file_get_contents($avatarUrl);
        // 3. 加载头像图片
        // $avatar = imagecreatefromstring($avatarUrl);
        // $avatarUrl = $this->createCircleImage($avatar);
        // 3. 加载头像图片
        //$avatarUrl = $this->loadImage($avatarUrl);

        // 4. 使用 Grafika 加载图片
        // $background = Grafika::createImage($backgroundPath);
        //$avatarUrl = Grafika::createImage($avatarUrl);


        //$avatar = $this->createCircleImage($editor, $avatarUrl);

        // 打开优惠券图片
        //$editor->open($productImage, $avatarUrl);
        // 重设优惠券图片宽高
        //$editor->resizeExact($productImage, 170, 170);



        // 用户头像
        //$editor->blend($backdropImage, $productImage, 'normal', 1.0, 'top-left', 280, 370);
        // 商品名称处理换行

        //$fontSize = 30;

        // 名称处理换行
        $fontSize = 25;
        $userName = $this->wrapText($fontSize, 0, $fontPath, $this->user['nickName'], 680, 2);
        // $userName = $this->wrapText($fontSize, 0, $fontPath, $this->user['mobile'], 880, 2);
        // 用户名字
        $editor->text($backdropImage, $userName, $fontSize, 340, 1100, new Color('#333333'), $fontPath);
        $count = UserModel::where('referee_id',$this->user['user_id'])->count();
        $money = BalanceLogModel::where('user_id',$this->user['user_id'])->where('scene','in',[70,71,80])->sum('money');

        //$editor->text($backdropImage, '邀请人数:'.$count, $fontSize, 125, 620, new Color('#333333'), $fontPath);

        //$editor->text($backdropImage, '返回佣金:'.$money, $fontSize, 425, 620, new Color('#333333'), $fontPath);
        // $editor->text($backdropImage, $userName, $fontSize, 870, 3020, new Color('#ff0000'), $fontPath);
        //$editor->text($backdropImage, $this->user['mobile'], 38, 62, 964, new Color('#ff4444'));
        // 打开小程序码
        $editor->open($qrcodeImage, $qrcode);
        // 重设小程序码宽高
        $editor->resizeExact($qrcodeImage, 400, 400);
        // $editor->resizeExact($qrcodeImage, 280, 280);
        // 小程序码添加到背景图
        $editor->blend($backdropImage, $qrcodeImage, 'normal', 1.0, 'top-left', 780, 1850);
        // $editor->blend($backdropImage, $qrcodeImage, 'normal', 1.0, 'top-left', 570, 3000);


        // 保存图片
        $editor->save($backdropImage, $this->getPosterPath());
        return $this->getPosterUrl();
    }

    /**
     * 处理文字超出长度自动换行
     */
    private function wrapText($fontsize, $angle, $fontface, $string, $width, $max_line = null)
    {
        // 这几个变量分别是 字体大小, 角度, 字体名称, 字符串, 预设宽度
        $content = "";
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        $letter = [];
        for ($i = 0; $i < mb_strlen($string, 'UTF-8'); $i++) {
            $letter[] = mb_substr($string, $i, 1, 'UTF-8');
        }
        $line_count = 0;
        foreach ($letter as $l) {
            $testbox = imagettfbbox($fontsize, $angle, $fontface, $content . ' ' . $l);
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                $line_count++;
                if ($max_line && $line_count >= $max_line) {
                    $content = mb_substr($content, 0, -1, 'UTF-8') . "...";
                    break;
                }
                $content .= "\n";
            }
            $content .= $l;
        }
        return $content;
    }


    /**
     * 海报图文件路径
     */
    private function getPosterPath()
    {
        // 保存路径
        $tempPath = root_path('public') . 'temp' . '/' . $this->user['app_id'] . '/' . $this->source. '/';
        !is_dir($tempPath) && mkdir($tempPath, 0755, true);
        return $tempPath . $this->getPosterName();
    }

    /**
     * 海报图文件名称
     */
    private function getPosterName()
    {
        return 'user_' . md5("{$this->user_id}") . '.png';
    }

    /**
     * 海报图url
     */
    private function getPosterUrl()
    {
        return \base_url() . 'temp/' . $this->user['app_id'] . '/' .$this->source . '/' . $this->getPosterName() . '?t=' . time();
    }

    /**
     * 将图片变成圆形
     *
     * @param \Grafika\EditorInterface $editor Grafika 编辑器
     * @param \Grafika\ImageInterface $image 图片对象
     * @return \Grafika\ImageInterface 圆形图片对象
     */
    private function createCircleImage($editor, $image)
    {
        $editor = Grafika::createEditor(['Gd']);
        $avatar = Grafika::createImage($image);
        // 1. 获取图片尺寸
        $width = $avatar->getWidth();
        $height = $avatar->getHeight();
        $size = min($width, $height); // 获取最小边

        // 2. 创建一个圆形遮罩
        $mask = Grafika::createBlankImage($size, $size);
        $mask = Grafika::createBlankImage($size, $size);


        $editor->fill($mask,  new Color('#FFFFFF')); // 填充白色背景
        // 3. 绘制圆形
        //$editor->draw($mask, 'ellipse', [0, 0, $size, $size], new Color('#000000')); // 绘制黑色圆形
        $editor->draw($mask, 'ellipse', [0, 0, $size, $size], new Color('#000000'));

        // 4. 将头像调整为正方形
        $editor->resizeExact($avatar, $size, $size);

        // 5. 应用圆形遮罩
        $editor->applyMask($avatar, $mask);

        return $avatar;
    }

    private function loadImage($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return imagecreatefromjpeg($path);
            case 'png':
                return imagecreatefrompng($path);
            case 'gif':
                return imagecreatefromgif($path);
            default:
                return false;
        }
    }


    public static function getQcPlayImg()
    {
        return 'https://www.chazhanglao.com/temp/img/qc_play.png';
    }

    public static function getDeclareImg()
    {
        return 'https://www.chazhanglao.com/temp/img/declare_img.png';
    }

}