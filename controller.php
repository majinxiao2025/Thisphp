<?php

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use JetBrains\PhpStorm\NoReturn;
use MaxMind\Db\Reader\InvalidDatabaseException;

/**
 * Copyright © 2017-2020 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2020/11/22
 * Time: 4:29 下午
 */

class controller extends core
{
    protected mixed $db;
    protected mixed $config;
    public function __construct()
    {
        $this->data= new stdClass();
        $this->config = $GLOBALS['config'];
        $this->db = inClass('postgresql');
        $this->db->owner = $this; //翻页超出范围时 cls_postgresql 借它调 not404()
    }
    /**
     * @param $array
     * @return string
     * 供前端使用的json格式
     */
    protected function bejson($array): string
    {
        return '('.json_encode($array).')';
    }
    /**
     * @param string $param 关键词等值
     * $this->db->limitNum 页面上显示数据的条数，根据该值显示最大页面数
     * @param int $pageNum 页面上显示的最大页码数量 偶数
     * @param int $pageMax 最大页码数/尾页数
     * @return string
     * 2021-04-10 17:40:58 维护更新
     * Update: 2021-11-05 18:49:31
     * update 2025-07-31 13:32:23
     */
    public function page(string $param = '' ,int $pageNum = 6, int $pageMax = 30): string
    {
        $echo = '';
        $page = max(1,(int)get('page'));
        $zPage = !empty($GLOBALS['total'])?ceil($GLOBALS['total']/$GLOBALS['limit']):$pageMax;
        $zPage = min($zPage, $pageMax);
        if($page>$zPage)return '';
        //输出到 href 前转义，防止 XSS
        $url = htmlspecialchars($param?'?'.$param:(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/'),ENT_QUOTES);
        $endParam = $param?'&':'?';
        $end = floor($pageNum/2)?:1;

        $echo.= '<li><a ';
        if($page-1>0){
            $echo.='href="'.$url.'"';
        }
        $echo.='>首页</a></li><li><a';
        if($page-1>0){$echo.=' href="'.$url.($page-1>1?$endParam.'page='.$page-1:'').'"';}
        $echo.='>上一页</a></li>';
        for($i=1; $i<=($pageNum<$zPage?$pageNum:$zPage) ;$i++){
            if($page < $end){
                if($i == $page){
                    $echo.='<li class="active"><b>'.$page.'</b></li>';
                }
                else{
                    $n = $i>1?$endParam.'page='.$i:'';
                    $echo.='<li><a href="'.$url.$n.'">'.$i.'</a></li>';
                }
            }
            //当page >=3 时，执行下方代码
            else{
                $endPage = $page+$i-$end;
                if($i < $end){
                    $prepage = $page-($end-$i);
                    $n2 = $prepage>1?$endParam.'page='.$prepage:'';
                    $echo.='<li><a class="p" href="'.$url.$n2.'">'.$prepage.'</a></li>';
                }
                else if($i == $end){
                    $echo.='<li class="active x"><b>'.$page.'</b><li>';
                }
                else if($endPage <= $zPage){
                    $echo.='<li><a class="z" href="'.$url.$endParam.'page='.$endPage.'">'.$endPage.'</a></li>';
                }
            }
        }
        if(($page+1)<=$zPage){$echo.='<li><a href="'.$url.$endParam.'page='.($page+1).'">下一页</a></li>';}
        return $echo;
    }

    /**
     * 字符串加密
     * 转成数字形式
     * [可以解密]
     * @param $string
     * @param string $split
     * @return string
     * update 2025-09-19 21:09:55
     */
    protected function token($string, string $split = ''): string{
        $letter='0123456789+/=aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ';
        $str=base64_encode($string);
        $arr=str_split($str);
        $letter=str_split($letter);
        $letter=array_flip($letter);
        $code='';
        foreach ($arr as $index){
            $code .= ($code?$split:'').sprintf('%02d',$letter[$index]);
        }
        return $code;
    }
    protected function deToken($code,$split = ''): string{
        $letter='0123456789+/=aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ';
        $letter = str_split($letter);
        $arr = $split?explode($split,$code):str_split($code,2);
        $str='';
        foreach ($arr as $index){
            $str.=$letter[intval($index)];
        }
        return base64_decode($str);
    }

    /**
     * @param $string
     * @return string
     * 2025-09-19 21:11:57
     * 转成字母
     */
    protected function tokenPath($string): string
    {
        $letter = 'abcdefhikl';
        $split = str_split($letter);
        $split['/'] = 'm';
        $string = str_split($string);
        foreach ($string as $key=>$index){
            $string[$key] = $split[$index];
        }
        return implode($string);
    }
    /**
     * 给登录态 uid 签名，返回可以放进 cookie 的字符串
     * token()/deToken() 只是可逆编码，字母表就在源码里，不能防伪造；
     * 登录态必须用带服务器端密钥(config->authSecret)的 hash_hmac 签名
     * @param int|string $uid
     * @return string
     * 2026-10-22 整合自 btba_www
     */
    protected function signUid(int|string $uid): string
    {
        if(empty($this->config->authSecret)){
            exit('config authSecret is empty');
        }
        return $uid.'.'.hash_hmac('sha256',(string)$uid,$this->config->authSecret);
    }

    /**
     * 校验签名过的 uid，篡改/伪造过的一律当未登录处理
     * @param mixed $signed
     * @return int|null
     * 2026-10-22 整合自 btba_www
     */
    protected function verifyUid(mixed $signed): ?int
    {
        if(empty($this->config->authSecret) || !is_string($signed) || !str_contains($signed,'.')){
            return null;
        }
        [$uid,$sig] = explode('.',$signed,2);
        if(!preg_match('/^\d{1,20}$/',$uid)){
            return null;
        }
        $expected = hash_hmac('sha256',$uid,$this->config->authSecret);
        if(!hash_equals($expected,$sig)){
            return null;
        }
        return (int)$uid;
    }

    /**
     * 签发邮箱/短信验证码，并记下发给了谁，配合 checkMailCode() 使用
     * @param string $key 这个流程的 session key，不同流程(注册/找回密码)用不同的 key
     * @param string $target 发送目标(邮箱或手机号)
     * @return string 6 位数字验证码
     * 2026-10-22 整合自 btba_www / btba_m
     */
    protected function issueMailCode(string $key, string $target): string
    {
        if(session_status() !== PHP_SESSION_ACTIVE){
            session_start();
        }
        $code = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $_SESSION[$key] = $code;
        $_SESSION[$key.'_time'] = time();
        $_SESSION[$key.'_target'] = strtolower(trim($target));
        $_SESSION[$key.'_fail'] = 0;
        return $code;
    }

    /**
     * 校验验证码：必须是发给这个目标的、10 分钟内有效、同一个验证码错 5 次作废、校验通过后作废
     * 不要直接用 $code == $_SESSION[...] 比较：没发过验证码时两边都是 null，会直接通过
     * @param string $key 同 issueMailCode()
     * @param string $target 用户这次提交的邮箱/手机号
     * @param mixed $code 用户提交的验证码
     * @param bool $consume 通过后是否作废；前端预检传 false，真正注册/重置时传 true
     * @return bool
     * 2026-10-22 整合自 btba_www / btba_m
     */
    protected function checkMailCode(string $key, string $target, mixed $code, bool $consume = true): bool
    {
        if(session_status() !== PHP_SESSION_ACTIVE){
            session_start();
        }
        $sessCode = $_SESSION[$key] ?? '';
        $sessTarget = $_SESSION[$key.'_target'] ?? '';
        if(!is_string($sessCode) || $sessCode === '' || time() - ($_SESSION[$key.'_time'] ?? 0) > 600){
            return false;
        }
        if(!is_scalar($code) || $sessTarget === '' || strtolower(trim($target)) !== $sessTarget || !hash_equals($sessCode,(string)$code)){
            $_SESSION[$key.'_fail'] = ($_SESSION[$key.'_fail'] ?? 0) + 1;
            if($_SESSION[$key.'_fail'] >= 5){
                unset($_SESSION[$key],$_SESSION[$key.'_time'],$_SESSION[$key.'_target'],$_SESSION[$key.'_fail']);
            }
            return false;
        }
        if($consume){
            unset($_SESSION[$key],$_SESSION[$key.'_time'],$_SESSION[$key.'_target'],$_SESSION[$key.'_fail']);
        }
        return true;
    }

    /**
     * 跳转目标是否是本站地址：相对路径(/xxx，不能是 //xxx)，或 config->siteRoots 白名单里的域名及其子域
     * 用户传进来的跳转地址(referer/back 等)必须先过这个，防开放重定向和 javascript: 伪协议
     * @param string $url
     * @return bool
     * 2026-10-22 整合自 btba_www / btba_m
     */
    protected function isSiteUrl(string $url): bool
    {
        if(preg_match('#^/(?![/\\\\])#',$url)){
            return true;
        }
        $scheme = parse_url($url,PHP_URL_SCHEME);
        $host = parse_url($url,PHP_URL_HOST);
        if(!in_array(strtolower((string)$scheme),['http','https'],true) || !is_string($host) || $host === ''){
            return false;
        }
        $host = strtolower($host);
        foreach ($this->config->siteRoots as $root){
            if($host === $root || str_ends_with($host,'.'.$root)){
                return true;
            }
        }
        return false;
    }

    /**
     * 按 IP 限流：时间窗口内超过次数就返回 true(依赖 cls_redis)
     * @param string $key 限流维度，如 'login'
     * @param int $max 窗口内允许的最大次数
     * @param int $windowSec 窗口秒数
     * @return bool 是否已超限
     * 2026-10-22 整合自 btba_www / btba_m
     */
    protected function rateLimited(string $key, int $max, int $windowSec): bool
    {
        $redis = inClass('redis')->redis;
        $rkey = 'rl:'.$key.':'.$this->getIp();
        $count = $redis->incr($rkey);
        if($count==1){
            $redis->expire($rkey,$windowSec);
        }
        return $count>$max;
    }
    /**
     * @param $str
     * @return array
     * 字符串加密
     */
    protected function strencode($str): array
    {
        //2026-10-22 原来调用的 RandKey() 不存在，一调用就 Fatal，改用随机字母
        $key1=strtoupper(substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz',3)),0,3));
        $key2=strtoupper(substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz',4)),0,4));
        $sign_code=$key1.base64_encode($str).$key2;
        $sign=base64_encode($sign_code);
        return array($sign,$key1,$key2);
    }
    /**
     * JSON 序列化
     * @param array $data
     * @param string $msg
     */
    protected function json($data=[],$msg=''){
        return json_encode([
            'result'=>'success',
            'msg'=>$msg,
            'data'=>$data
        ],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);//|JSON_UNESCAPED_UNICODE
    }

    /**
     * 输出JSON
     * @param array $data
     * @param string $msg
     */
    protected function echo_json($data=[],$msg=''){
        //声明为 JSON，避免浏览器按 HTML 解析产生 XSS
        if(!headers_sent())header('Content-Type: application/json; charset=UTF-8');
        echo $this->json($data,$msg);
    }
    /**
     * 静态资源地址统一出口：CDN 地址 + ?v=本地同名文件的修改时间
     * 改完 css/js 后 URL 自动带上新版本号，CDN 按 URL 缓存会自动回源，不用手动刷新缓存
     * 本地没有对应文件时退化为不带版本号
     * @param string $path 相对 external/static/ 的资源路径，如 'css/index.css'
     * @return string
     * 2026-10-22 整合自 btba_www
     */
    protected function asset(string $path): string
    {
        $ver = @filemtime('external/static/'.$path);
        return $this->config->https->cdn.$path.($ver ? '?v='.$ver : '');
    }

    /**
     * 把绝对地址里的自家根域换成当前访问的根域(多域名站点用)
     * 只替换 config->siteRoots 白名单里的域名，外站链接和相对路径原样返回
     * @param string|null $url
     * @return string
     * 2026-10-22 整合自 btba_www
     */
    protected function siteLink(?string $url): string
    {
        $url = (string)$url;
        $host = parse_url($url,PHP_URL_HOST);
        if(!is_string($host) || $host === ''){
            return $url;   //相对路径不用动
        }
        $host = strtolower($host);
        $current = $this->config->siteRoot;
        foreach ($this->config->siteRoots as $root){
            if($host === $root || str_ends_with($host,'.'.$root)){
                if($root === $current){
                    return $url;
                }
                //只替换结尾那段根域，子域前缀(bbs./m./www.)保留
                $newHost = substr($host,0,strlen($host)-strlen($root)).$current;
                return str_replace('//'.$host,'//'.$newHost,$url);
            }
        }
        return $url;   //外站
    }
    /**
     * @param string $url
     * 2021-12-01 20:10:45
     */
    #[NoReturn]
    protected function location(string $url='/'): void
    {
        header("location: $url");exit;
    }

    /**
     * @return false|mixed|string
     * 2026-04-02 13:48:51
     * 获取客户端 IP
     */
    protected function getIp(): mixed
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        //X-Forwarded-For 可被客户端伪造，只有请求来自 config.php 中信任的代理时才采用
        //update 2026-10-22
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && in_array($ip, $this->config->trusted_proxies ?? [], true)) {
            $forwarded = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) $ip = $forwarded;
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '未获取到IP';
    }
}