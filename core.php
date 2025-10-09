<?php
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
        $viewPath = 'view/' . $GLOBALS['config']->version .'/'.($filename ?:$_REQUEST['class'].'/'.$_REQUEST['method'].$GLOBALS['config']->view_ext).'.php';//print_r($viewPath);
        $Page404 = $this->page404Path();
        if (file_exists($viewPath)) {
            include "$viewPath";
        } else if (file_exists($Page404)) {
            include $Page404;
        } else {
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
        $int=substr(str_replace('.','',microtime(true)),0,$length);
        $str = str_shuffle($int);
        $str[0] = $str[0]?:1;
        return $str;
    }

    /**
     * 404 PAGE
     * 2021-11-27 10:35:16
     */
    public function not404(){
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