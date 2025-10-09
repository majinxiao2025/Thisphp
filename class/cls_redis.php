<?php
/**
 * Copyright © 2017-2025 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2025/8/14
 * Time: 10:10
 */
class cls_redis
{
    public Redis $redis;
    public function __construct($className){
        $redis = new Redis();
        $result = $redis->connect('127.0.0.1', 6600);//,['auth'=>'makin123*']
        if(!$result){
            exit('Server is error!');
        }
        $redis->select(0);
        $this->redis=$redis;
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
     * @param $wd
     * @return float|false|Redis
     * 搜索词入库
     * 2025-08-14 10:16:52
     */
    public function index($wd,$data): float|false|Redis
    {
        $did = $this->RandInt();
        $this->redis->hSet('keys_data',$did,$data);
        $this->redis->lPush('keys_did',$did);
        //热门搜索词
        return $this->redis->zIncrBy('keys_hot',1,$wd);

    }
}