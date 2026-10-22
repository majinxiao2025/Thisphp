<?php
/**
 * Copyright © 2017-2020 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2020/10/15
 * Time: 1:23 上午
 * Program Thisphp 6.0
 */
$GLOBALS['config'] = require_once("config.php");
//路由参数只允许字母数字下划线，防止目录穿越/文件包含 2026-10-22
$route = (object)[
    'class'=>is_string($_GET['class'] ?? null) ? $_GET['class'] : 'index',
    'method'=>is_string($_GET['method'] ?? null) ? $_GET['method'] : 'index',
];
if(!preg_match('/^[a-z_]{1,20}$/',$route->class) || !preg_match('/^[a-zA-Z0-9_]{1,20}$/',$route->method)){
    header("HTTP/1.0 404 Not Found");exit('ERROR 404 !');
}
$GLOBALS['route'] = $route;
$class = 'clr_'.$route->class;
$method = $route->method;
$controllerFilePath = "controller/$class.php";
if(!is_file($controllerFilePath)){header("location: /");exit;}
header("Program: This 8.1");
require_once "core.php";
require_once "controller.php";
require_once $controllerFilePath;
$index = new $class;
//只允许调用控制器自身声明的 public 非静态方法，禁止调用 __construct 等魔术方法及父类的 view/page/not404 等
//不满足时走 not404()，返回真正的 404 状态码，避免"软 404"(整合自 btba_www)
if (!method_exists($class,$method) || str_starts_with($method,'__')){$index->not404();}
$reflection = new ReflectionMethod($class,$method);
if (!$reflection->isPublic() || $reflection->isStatic() || $reflection->getDeclaringClass()->getName() !== $class){$index->not404();}
/* ?page= 统一在入口校验：只认正整数，空值当没传，其它一律 404。
   后面所有用到 page 的地方(分页、SQL offset、视图输出)拿到的都是干净的数字串 2026-10-22 整合自 btba_www */
if(isset($_GET['page'])){
    $rawPage = $_GET['page'];
    if($rawPage === ''){
        unset($_GET['page']);
    }elseif(!is_string($rawPage) || !ctype_digit($rawPage) || (int)$rawPage < 1){
        $index->not404();
    }else{
        $_GET['page'] = (string)(int)$rawPage;
    }
}
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
            return $_GET[$name] ?? null;
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
            return $_POST[$name] ?? null;
        }
    }
    return (object)$_POST;
}
/**
 * @return mixed
 * 2026-01-29 17:52:13
 * 处理前端发送的JSON数据格式
 */
function json(): mixed
{
    return json_decode(file_get_contents('php://input'));
}