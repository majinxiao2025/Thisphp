<?php
/**
 * Copyright © 2017-2020 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2020/11/9
 * Time: 2:52 下午
 *
 */

class cls_mysqli
{
    public $pre = 'bt';
    public function __construct($className){
        $this->mysqli = new mysqli('127.0.0.1','root','123456','main_db','7002');
    }

    /**
     * @param string $field
     * @param $table
     * @param string $where
     * @return bool|mysqli_result
     */
    public function select($field="*",$table,$where=""){
        return $this->mysqli->query('select '.$field.' from '.$this->pre.'_'.$table.' '.$where)->fetch_object();
    }

}