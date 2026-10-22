<?php
/**
 * Copyright © 2019 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2019-07-07
 * Time: 15:39
 */

class cls_verCode
{
    /**
     * 共用验证码函数
     * @param object $object
     */
    public function showCode($object=null){
        //2026-10-22 原来 $object 为空时直接给 null 赋属性会报错，这里先建对象
        if(!$object){
            $object = new stdClass();
            $object->ver_string='code';
            $object->fontColor='#000000';
            $object->bgColor='#ffffff';
        }
        session_start();
        $rand=$this->RandCode(4);
        $fc=$this->hex2rgb($object->fontColor);
        $bc=$this->hex2rgb($object->bgColor);
        #重新赋值，因为变量rand可能改变
        $_SESSION[$object->ver_string]=strtolower($rand);
        $width='80';
        $height='30';
        $img=imagecreatetruecolor($width,$height);
        //设置颜色
        $bg=imagecolorallocate($img,$bc->red,$bc->green,$bc->blue);#背景颜色
        imagefill($img,0,0,$bg);
        $te=imagecolorallocate($img,$fc->red,$fc->green,$fc->blue);#字体颜色
        imagestring($img,6,rand(0,$width/2),6,$rand,$te);//图片,字体1-6之间,X轴,Y轴,文字,字体颜色
        header("content-type: image/jpeg");
        imagejpeg($img);
    }

    /**
     * HEX值转换成RGB
     * @param $colour
     * @return object|bool|array
     */
    public function hex2rgb( $colour ): object|bool|array
    {
        if ( $colour[0] == '#' ) {
            $colour = substr( $colour, 1 );
        }
        if ( strlen( $colour ) == 6 ) {
            list( $r, $g, $b ) = array( $colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5] );
        } elseif ( strlen( $colour ) == 3 ) {
            list( $r, $g, $b ) = array( $colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2] );
        } else {
            return false;
        }
        $r = hexdec( $r );
        $g = hexdec( $g );
        $b = hexdec( $b );
        return (object)array( 'red' => $r, 'green' => $g, 'blue' => $b );
    }
    /**
     * 随机码
     * @param $x
     * @return string
     */
    private function RandCode($x): string
    {
        $r='abcdefghijklmnopqrstuvwxyz0123456789';$echo='';
        for($i=0;$i<$x;$i++){$echo.=$r[random_int(0,strlen($r)-1)];}
        return $echo;
    }
    public function captcha(): void
    {

        /**
         * captcha.php
         * 生成图片验证码，风格与前端 Canvas 演示保持一致：
         * 深色背景 + 彩色扭曲字符 + 干扰线/干扰点
         *
         * 用法：<img src="captcha.php" onclick="this.src='captcha.php?t='+Date.now()">
         * 依赖：PHP GD 扩展（需开启 gd），建议提供一个等宽字体 ttf 文件以获得旋转效果
         */

        session_start();

        // ------------------ 可调配置 ------------------
        $width = 120;              // 图片宽度，与前端 canvas 尺寸一致
        $height = 44;               // 图片高度
        $codeLength = 4;                // 验证码位数
        $fontSize = 20;                // 字体大小（使用 TTF 时生效）
        $expireSecs = 300;               // 验证码有效期（秒），verify_captcha.php 里会用到
        // 字符集：排除易混淆字符 0/O、1/I/l
        $charset = 'ABCDEFGHJKMNPQRSTUVWXY346789';
        // TTF 字体路径：建议放一个等宽字体（如 IBM Plex Mono / DejaVu Sans Mono）到 fonts 目录
        // 找不到字体文件时会自动降级为 GD 内置位图字体（无法旋转，效果会差一些）
        $fontPath = $_SERVER['DOCUMENT_ROOT'] . '/external/fonts/DejaVuSansMono-Bold.ttf';

        // ------------------ 生成随机验证码 ------------------
        $code = '';
        $max = strlen($charset) - 1;
        for ($i = 0; $i < $codeLength; $i++) {
            $code .= $charset[random_int(0, $max)];
        }

        // 存入 Session，供 verify_captcha.php 校验
        $_SESSION['captcha_code'] = $code;   // 校验时统一转大写比较，这里不区分大小写存储也可
        $_SESSION['captcha_time'] = time();

        // ------------------ 创建画布 ------------------
        $im = imagecreatetruecolor($width, $height);
        imagealphablending($im, true);

        // 背景色 #171C22（与前端 --surface-2 一致）
        $bgColor = imagecolorallocate($im, 0x17, 0x1C, 0x22);
        imagefill($im, 0, 0, $bgColor);

        // ------------------ 干扰线（半透明绿色，呼应主色调） ------------------
        for ($i = 0; $i < 4; $i++) {
            // GD 的 alpha 范围是 0(不透明) - 127(全透明)，数值越大越淡
            $alpha = random_int(95, 112);
            $lineColor = imagecolorallocatealpha($im, 0x35, 0xD0, 0x7F, $alpha);
            imageline(
                $im,
                random_int(0, $width), random_int(0, $height),
                random_int(0, $width), random_int(0, $height),
                $lineColor
            );
        }

        // ------------------ 干扰点 ------------------
        for ($i = 0; $i < 40; $i++) {
            $alpha = random_int(95, 118);
            $dotColor = imagecolorallocatealpha($im, 255, 255, 255, $alpha);
            imagesetpixel($im, random_int(0, $width - 1), random_int(0, $height - 1), $dotColor);
        }

        // ------------------ 字符配色（对应前端 accent / info / warn 配色） ------------------
        $palette = [
            [0xE7, 0xEC, 0xEF], // 主文字色
            [0x4F, 0xA8, 0xFF], // 蓝 info
            [0x35, 0xD0, 0x7F], // 绿 accent
            [0xF5, 0xA6, 0x23], // 橙 warn
        ];

        $hasFont = is_file($fontPath);
        $startX = 16;
        $step = ($width - $startX * 2) / $codeLength;

        for ($i = 0; $i < $codeLength; $i++) {
            $rgb = $palette[$i % count($palette)];
            $color = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
            $char = $code[$i];
            $x = (int)round($startX + $i * $step + random_int(-2, 2));
            $y = (int)round($height / 2 + random_int(-3, 3));

            if ($hasFont) {
                // imagettftext 的 y 是文字基线位置，+7 大致做垂直居中
                $angle = random_int(-18, 18);
                imagettftext($im, $fontSize, $angle, $x, $y + 7, $color, $fontPath, $char);
            } else {
                // 没有 TTF 字体时的退化方案：内置位图字体，无法旋转，仅保证功能可用
                imagestring($im, 5, $x, (int)($height / 2 - 8), $char, $color);
            }
        }

        // ------------------ 输出图片 ------------------
        // 优先输出 WebP：体积更小、加载更快；如果当前 GD 没编译 libwebp 支持，自动回退成 PNG
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $webpQuality = 80; // 0-100，验证码这种小图 75-85 之间肉眼基本无损

        if (function_exists('imagewebp')) {
            header('Content-Type: image/webp');
            imagewebp($im, null, $webpQuality);
        } else {
            header('Content-Type: image/png');
            imagepng($im);
        }
    }

    /**
     * 校验 captcha() 生成的验证码：不区分大小写，超过有效期作废，校验一次后立即销毁(防止重放/暴力枚举)
     * @param string $input 用户输入的验证码
     * @param int $expireSecs 有效期(秒)，与 captcha() 中的 $expireSecs 保持一致
     * @return bool
     * 2026-10-22
     */
    public function checkCaptcha(string $input, int $expireSecs = 300): bool
    {
        if(session_status() !== PHP_SESSION_ACTIVE){
            session_start();
        }
        $code = $_SESSION['captcha_code'] ?? '';
        $time = $_SESSION['captcha_time'] ?? 0;
        unset($_SESSION['captcha_code'],$_SESSION['captcha_time']);
        if($code === '' || time() - $time > $expireSecs){
            return false;
        }
        return hash_equals($code, strtoupper(trim($input)));
    }
}
