<?php

use JetBrains\PhpStorm\NoReturn;

/**
 * Class core
 * 2021-07-21 12:17:18
 */
class core
{
    protected mixed $data;
    /**
     * View
     * @param bool|string $data
     * @param false $filename
     */
    public function view(bool|string $filename = false, bool $data=false){
        if($data)$this->data = $data;
        //使用 index.php 校验过的路由，不能用 $_REQUEST（POST/COOKIE 可覆盖，导致任意文件包含）
        $viewPath = 'view/' . $GLOBALS['config']->version .'/'.($filename ?:$GLOBALS['route']->class.'/'.$GLOBALS['route']->method.$GLOBALS['config']->view_ext).'.php';//print_r($viewPath);
        $Page404 = $this->page404Path();
        if (file_exists($viewPath)) {
            include "$viewPath";
        } else if (file_exists($Page404)) {
            //补上 404 状态码，跟 not404() 保持一致，避免"软 404"(内容说没找到，状态码却是 200) 2026-10-22 整合自 btba_www
            header("HTTP/1.0 404 Not Found");
            header("Status: 404 Not Found");
            include $Page404;
        } else {
            header("HTTP/1.0 404 Not Found");
            header("Status: 404 Not Found");
            exit('webpage is not found!');
        }
        return $viewPath;
    }
    private function page404Path(): string
    {
        return 'view/' . $GLOBALS['config']->version . '/' . $GLOBALS['config']->page404;
    }
    /**
     * 输出随机数字字符串
     * 永远不是0开头
     * @param int $length
     * @return string
     */
    protected function RandInt(int $length=10): string
    {
        //使用 random_int 生成，microtime 打乱可被预测 2026-10-22
        $str = (string)random_int(1,9);
        for($i=1; $i<$length; $i++){
            $str .= random_int(0,9);
        }
        return $str;
    }

    /**
     * 404 PAGE
     * 2021-11-27 10:35:16
     */
    #[NoReturn] public function not404(){
        header("HTTP/1.0 404 Not Found");
        header("Status: 404 Not Found");
        $page404 =$this->page404Path();
        if(file_exists($page404)){
            include $page404;exit;
        }
        else{
            exit('ERROR 404 !');
        }
    }
}