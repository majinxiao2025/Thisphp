<?php
/**
 * Copyright © 2016 Braveten computer development Co. Ltd.
 * Engineer: makin
 * Date: 2016/12/20
 * Time: 上午12:32
 */

/**
 * Class curl
 * $data = array(); array('user'=>'username','password'=>'12345678');
 * $cookie_path=string 如果cookie_path 有值 就启用cookie保存 值为cookie保存的路径
 */
class cls_curl
{
    public array $data=array();
    private $cookie_path;
    public $header=array();
    public $host;//供给外部使用
    public bool $get_header = false;
    public function host($url){
        $parse=parse_url($url);
        $this->host=$parse['host'];
        return $parse['scheme'].'//:'.$parse['host'];
    }

    public function enable_cookie($url){
        $pu=parse_url($url);
        $dir=$_SERVER['DOCUMENT_ROOT'].'/cookie/';
        if(!is_dir($dir)){
            mkdir($dir);
        }
        $this->cookie_path=$dir.$pu['host'];
    }
    public function get($url,$cookie=false,$header=false){
        $this->host($url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS,3000);// 连接超时（毫秒）
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不验证证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //不验证证书
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
        curl_setopt($ch, CURLOPT_HEADER,$this->get_header);

        if($header===true){
            curl_setopt($ch, CURLOPT_HTTPHEADER,$this->header());
        }
        elseif (is_array($header)){
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        }

        if($cookie){
            $this->enable_cookie($url);
            curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookie_path);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookie_path);
        }
        $content=curl_exec($ch);
        curl_close($ch);
        return $this->iconv($content);
    }

    /**
     * @param $url
     * @param bool $cookie
     * @param bool $header
     * @return mixed
     */
    public function post($url, bool $cookie=false, bool|array $header=false){
        $this->host($url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url) ;
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$this->data);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS,3000);// 连接超时（毫秒）
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);// 执行超时（秒）
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header?:$this->header());
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名

        if($cookie){
            $this->enable_cookie($url);
            curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookie_path);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookie_path);
        }
        $content=curl_exec($ch);
        curl_close($ch);
        return $content;
    }
    public function get_batch($url_arr=array(),$cookie=false,$header=false){
        // 创建批处理cURL句柄
        $mh = curl_multi_init();
        $handles=[];
        foreach ($url_arr as $url){
            $this->host($url);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$url) ;
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS,3000);// 连接超时（毫秒）
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);//接收信息超时
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header?$header:$this->header());
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不验证证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //不验证证书
            if($cookie){
                $this->enable_cookie($url);
                curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookie_path);
                curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookie_path);
            }
            $handles[$url] = $ch;
            curl_multi_add_handle($mh,$ch);
        }
        $running=null;
        $responses=[];
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($mh, $running) ;
        } while($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($running && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $running);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        foreach ($handles as $ch){
            //获取文本流
            $responses[] = $this->iconv(curl_multi_getcontent($ch));
            // 关闭全部句柄
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $responses;
    }

    /**
     * @param array $url_arr
     * @param bool $cookie
     * @param bool $header
     * @return array
     * 批量处理请求
     */
    public function post_batch($url_arr=array(),$cookie=false,$header=false){

        // 创建批处理cURL句柄
        $mh = curl_multi_init();
        $handles=[];
        foreach ($url_arr as $url){
            $this->host($url);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$url) ;
            curl_setopt($ch, CURLOPT_POST,1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$this->data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header?$header:$this->header());
            if($cookie){
                $this->enable_cookie($url);
                curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookie_path);
                curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookie_path);
            }
            $handles[$url] = $ch;
            curl_multi_add_handle($mh,$ch);
        }
        $running=null;
        $responses=[];
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($mh, $running) ;
        } while($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($running && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $running);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        foreach ($handles as $ch){
            //获取文本流
            $responses[] = curl_multi_getcontent($ch);
            // 关闭全部句柄
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $responses;
    }
    public function proxy_get($url,$proxy_ip='127.0.0.1:80',$cookie=false){
        $this->host($url);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_PROXY,"http://".$proxy_ip);//
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不验证证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //不验证证书
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);// 执行超时（秒）
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
        curl_setopt($ch, CURLOPT_HTTPHEADER,$this->header());
        if($cookie){
            $this->enable_cookie($url);
            curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookie_path);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookie_path);
        }
        $content=curl_exec($ch);
        curl_close($ch);
        return $content;
    }

    /**
     * @param $url
     * @param $proxy_ip
     * @param bool $cookie
     * @return mixed
     * proxy post
     */
    public function proxy_post($url,$proxy_ip,$cookie=true){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_PROXY,'http://'.$proxy_ip);
        curl_setopt($ch, CURLOPT_URL,$url) ;
        curl_setopt($ch, CURLOPT_POST,count($this->data));
        curl_setopt($ch, CURLOPT_POSTFIELDS,$this->data);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$this->header);

        if($cookie){
            $this->enable_cookie($url);
            curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookie_path);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookie_path);
        }
        $content=curl_exec($ch);
        curl_close($ch);
        return $content;
    }
    /**
     * 获取请求的状态码
     * @param $url
     * @param false $cookie
     * @return mixed
     */
    public function getHttpCode($url,$cookie=false): mixed
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS,3000);// 连接超时（毫秒）
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不验证证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //不验证证书
        //curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0); //强制协议为1.0
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); //强制使用IPV4协议解析域名
        if($cookie){
            $this->enable_cookie($url);
            curl_setopt($ch, CURLOPT_COOKIEJAR,$this->cookie_path);
            curl_setopt($ch, CURLOPT_COOKIEFILE,$this->cookie_path);
        }
        curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpCode;
    }
    /**
     * 如果是gbk或者gb2312就转成uft-8
     * @param $content
     * @return string
     */
    private function iconv($content){
        preg_match('/charset=(.*)\>/isU',$content,$preg);
        if(strpos($preg[1],'gb')!==false){
            return iconv('gbk','utf-8//IGNORE',$content);

        }else{
            return $content;
        }
    }
    private function header($ip=false): array
    {
        $ip=$ip?: (rand(1,255).'.'.rand(0,255).'.'.rand(0,255).'.'.rand(0,255));
        $client=array(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/603.1.30 (KHTML, like Gecko) Version/10.1 Safari/603.1.30', 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36 OPR/26.0.1656.60', 'Mozilla/5.0 (Windows NT 5.1; U; en; rv:1.8.1) Gecko/20061208 Firefox/2.0.0 Opera 9.50'
        );
        return array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8'
            ,'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'
            ,'Cache-Control: no-cache'
            ,'Connection: keep-alive'
            ,'Pragma: no-cache'
            ,'X-Requested-With: XMLHttpRequest'
            ,'client-ip:'.$ip
            ,'X-FORWARDED-FOR:'.$ip
            ,'User-Agent:'.$client[rand(0,count($client)-1)]
            ,'Host:'.$this->host
            ,'referer:'.$this->host
        );
    }
}