<?php
/**
 * Copyright © 2017-2020 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2020/10/15
 * Time: 1:23 上午
 * Program Thisphp 6.0
 */
$GLOBALS['config'] = require_once("config.php");
$class = 'clr_'.$_GET['class'];
$method = $_GET['method'];
$controllerFilePath = "controller/$class.php";
if(!file_exists($controllerFilePath)){header("location: /");exit;}
header("Program: This 8.1");
require_once "core.php";
require_once "controller.php";
require_once "controller/$class.php";
$index = new $class;
if (!method_exists($index,$method)){exit('method is not exist');}
$index->$method();

/**
 * @param $className
 * @param string $params
 * @return mixed
 */
function inClass($className, string $params=''): mixed
{
    $className = 'cls_'.$className;
    include_once "class/$className.php";
    return new $className($params);
}
/**
 * @param $controllerName
 * @param string $params
 * @return mixed
 * 引入控制器，作为控制器与控制器之间通信用
 * 2021-08-07 10:53:21
 */
function inController($controllerName,string $params=''): mixed
{
    $controller = 'clr_'.$controllerName;
    include_once "controller/$controller.php";
    return new $controller($params);
}

/**
 * @param bool|string $name
 * @param bool|string $val
 * @return mixed
 */
function get(bool|string $name=false, mixed $val=false): mixed
{
    if($name){
        if($val){
            $_GET[$name]=$val;
        }else{
            return $_GET[$name];
        }
    }
    unset($_GET['class']);
    unset($_GET['method']);
    return (object)$_GET;
}

/**
 * @param bool|string $name
 * @param bool|string $val
 * @return mixed
 */
function post(bool|string $name=false, bool|string $val=false): mixed
{
    if($name){
        if($val){
            $_POST[$name]=$val;
        }else{
            return $_POST[$name];
        }
    }
    return (object)$_POST;
}