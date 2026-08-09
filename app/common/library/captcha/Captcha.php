<?php

namespace app\common\library\captcha;

class Captcha
{
    private $im = null; // 验证码图片实例
    private $color = null; // 验证码字体颜色
    // 使用背景图片
    protected $useImgBg = false;
    // 验证码字体大小(px)
    protected $fontSize = 50;
    // 是否画混淆曲线
    protected $useCurve = false;
    // 是否添加杂点
    protected $useNoise = true;
    // 验证码图片高度
    protected $imageH = 0;
    // 验证码图片宽度
    protected $imageW = 0;
    // 验证码字体，不设置随机获取
    protected $fontttf = '';
    // 背景颜色
    protected $bg = [243, 251, 254];
    // 使用中文验证码
    protected $useZh = false;
    // 验证码位数
    protected $length = 4;
    //算术验证码
    protected $math = false;

    /**
     * 输出验证码图片
     * @access public
     * @param $generator : uniqid:key,
     * @return array
     */
    public function create($generator)
    {
        $uniqid = $generator['uniqid'];
        // 图片宽(px)
        $this->imageW || $this->imageW = $this->length * $this->fontSize * 1.5 + $this->length * $this->fontSize / 2;
        // 图片高(px)
        $this->imageH || $this->imageH = $this->fontSize * 2.5;

        // 建立一幅 $this->imageW x $this->imageH 的图像
        $this->im = imagecreate(intval($this->imageW), intval($this->imageH));
        // 设置背景
        imagecolorallocate($this->im, $this->bg[0], $this->bg[1], $this->bg[2]);

        // 验证码字体随机颜色
        $this->color = imagecolorallocate($this->im, mt_rand(1, 150), mt_rand(1, 150), mt_rand(1, 150));

        // 验证码使用随机字体
        $ttfPath = __DIR__ . '/fonts/' . ($this->useZh ? 'zhttfs' : 'ttfs') . '/';

        if (empty($this->fontttf)) {
            $dir = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if ('.' != $file[0] && substr($file, -4) == '.ttf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();
            $this->fontttf = $ttfs[array_rand($ttfs)];
        }

        $fontttf = $ttfPath . $this->fontttf;

        if ($this->useImgBg) {
            $this->background();
        }

        if ($this->useNoise) {
            // 绘杂点
            $this->writeNoise();
        }
        if ($this->useCurve) {
            // 绘干扰线
            $this->writeCurve();
        }

        // 绘验证码
        $text = $this->useZh ? preg_split('/(?<!^)(?!$)/u', $generator['value']) : str_split($generator['value']); // 验证码

        foreach ($text as $index => $char) {
            //mt_rand(1.2, 1.6) *
            $x = $this->fontSize * ($index + 1) * ($this->math ? 1 : 1.5);
            $y = $this->fontSize + mt_rand(10, 20);
            $angle = $this->math ? 0 : mt_rand(-40, 40);

            imagettftext($this->im, $this->fontSize, $angle, intval($x), intval($y), intval($this->color), $fontttf, $char);
        }

        ob_start();
        // 输出图像
        imagepng($this->im);
        $content = ob_get_clean();
        imagedestroy($this->im);
        $base64 = chunk_split(base64_encode($content));
        $data = [
            "uniqid" => $uniqid,
            "image" => "data:image/jpg/png/gif;base64," . $base64,
            //"code"=>$generator['value'],
        ];
        return $data;
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     *
     *      高中的数学公式咋都忘了涅，写出来
     *        正弦型函数解析式：y=Asin(ωx+φ)+b
     *      各常数值对函数图像的影响：
     *        A：决定峰值（即纵向拉伸压缩的倍数）
     *        b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
     *        φ：决定波形与X轴位置关系或横向移动距离（左加右减）
     *        ω：决定周期（最小正周期T=2π/∣ω∣）
     *
     */
    protected function writeCurve(): void
    {
        $px = $py = 0;

        // 曲线前部分
        $A = mt_rand(1, intval($this->imageH / 2)); // 振幅
        $b = mt_rand(intval(-$this->imageH / 4), intval($this->imageH / 4)); // Y轴方向偏移量
        $f = mt_rand(intval(-$this->imageH / 4), intval($this->imageH / 4)); // X轴方向偏移量
        $T = mt_rand(intval($this->imageH), intval($this->imageW * 2)); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand(intval($this->imageW / 2), intval($this->imageW * 0.8)); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i = (int)($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), intval($this->color));
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A = mt_rand(1, intval($this->imageH / 2)); // 振幅
        $f = mt_rand(intval(-$this->imageH / 4), intval($this->imageH / 4)); // X轴方向偏移量
        $T = mt_rand(intval($this->imageH), intval($this->imageW * 2)); // 周期
        $w = (2 * M_PI) / $T;
        $b = $py - $A * sin($w * $px + $f) - $this->imageH / 2;
        $px1 = $px2;
        $px2 = $this->imageW;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if (0 != $w) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageH / 2; // y = Asin(ωx+φ) + b
                $i = (int)($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->im, intval($px + $i), intval($py + $i), intval($this->color));
                    $i--;
                }
            }
        }
    }

    /**
     * 画杂点
     * 往图片上写不同颜色的字母或数字
     */
    protected function writeNoise(): void
    {
        $codeSet = '2345678abcdefhijkmnpqrstuvwxyz';
        for ($i = 0; $i < 10; $i++) {
            //杂点颜色
            $noiseColor = imagecolorallocate($this->im, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring($this->im, 5, mt_rand(-10, intval($this->imageW)), mt_rand(-10, intval($this->imageH)), $codeSet[mt_rand(0, 29)], $noiseColor);
            }
        }
    }

    /**
     * 绘制背景图片
     * 注：如果验证码输出图片比较大，将占用比较多的系统资源
     */
    protected function background(): void
    {
        $path = __DIR__ . '/bgs/';
        $dir = dir($path);

        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ('.' != $file[0] && substr($file, -4) == '.jpg') {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();

        $gb = $bgs[array_rand($bgs)];

        list($width, $height) = @getimagesize($gb);
        // Resample
        $bgImage = @imagecreatefromjpeg($gb);
        @imagecopyresampled($this->im, $bgImage, 0, 0, 0, 0, $this->imageW, $this->imageH, $width, $height);
        @imagedestroy($bgImage);
    }
}