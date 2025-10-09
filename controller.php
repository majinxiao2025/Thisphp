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
        $page = get('page')?:1;
        $url =$param?'?'.$param:parse_url($_SERVER['REQUEST_URI'])['path'];
        $endParam = $param?'&':'?';
        $zPage = $GLOBALS['total']?ceil($GLOBALS['total']/$GLOBALS['limit']):$pageMax;
        if($page>$zPage)return false;
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
     * @param $str
     * @return array
     * 字符串加密
     */
    protected function strencode($str): array
    {
        $key1=strtoupper($this->RandKey(3));
        $key2=strtoupper($this->RandKey(4));
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
        echo $this->json($data,$msg);
    }
    /**
     * @param string $url
     * 2021-12-01 20:10:45
     */
    protected function location(string $url='/'): void
    {
        header("location: $url");exit;
    }
}