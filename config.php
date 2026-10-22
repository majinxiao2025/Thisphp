<?php
/**
 * Copyright © 2017-2025 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2020/11/22
 * Time: 3:31 下午
 */
/* 多域名(2026-10-22 整合自 btba_www)：同一个站绑定多个根域时，站内链接跟着当前访问的域名走。
   必须用白名单，不能直接拿 $_SERVER['HTTP_HOST'] 拼：Host 是客户端可以伪造的请求头(Host 头注入)，
   直接用它拼链接或 cookie domain，可被用来钓鱼或投毒共享缓存。
   对不上白名单的 Host(以及 CLI 下没有 HTTP_HOST)一律落回 $siteRoots[0]。 */
$siteRoots = ['x.com'];
$siteHost  = strtolower(preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST'] ?? ''));
$siteRoot  = $siteRoots[0];
foreach ($siteRoots as $r) {
    if ($siteHost === $r || str_ends_with($siteHost,'.'.$r)) { $siteRoot = $r; break; }
}
$siteWww = 'https://www.'.$siteRoot.'/';

return (object)[
    'version'=>'1.0',
    //视图文件扩展名
    'view_ext'=>'',//.min.php
    'page404'=>'404.html',
    //域名白名单和本次命中的根域，controller::siteLink() 等处共用同一份
    'siteRoots'=>$siteRoots,
    'siteRoot'=>$siteRoot,
    //登录态签名密钥，controller::signUid()/verifyUid() 使用；只在服务器端使用，部署时务必改成随机长字符串
    //生成方式：php -r 'echo bin2hex(random_bytes(32));'
    'authSecret'=>'',
    //信任的反向代理IP，只有来自这些IP的请求才读取 X-Forwarded-For
    'trusted_proxies'=>['127.0.0.1','::1'],
    //Meilisearch 前台检索：key 请使用只有 search 权限的 key，不要用 master key
    'meilisearch'=>(object)[
        'url'=>'http://127.0.0.1:7700',
        'key'=>'',
        'index'=>'index_db'
    ],
    'cookie'=>(object)[
        'domain'=>'',
        'nickname'=>''
    ],
    'session'=>(object)[
        'register_ver'=>'register_ver',
        'resetpwd_ver'=>'resetpwd_ver',
        'id_name'=>'user_id'
    ],
    'send'=>(object)array(
        'mobile_url'=>'' //未使用
        ,'email'=>(object)array(
            'smtp'=>array('smtpdm.aliyun.com'),
            'user'=>array(''),
            'pwd'=>array('')
        )
    ),
    //自定义后端服务器接口
    'http'=>(object)[
        'admin'=>'',
        'image'=>'',
        'file'=>''
    ],
    'https'=>(object)[
        'index'=>$siteWww,
        //静态资源 CDN 前缀，controller::asset() 使用
        'cdn'=>'/external/static/',
    ],
    'info'=>(object)array(
        'sitename'=>'站点名称'
    )
];
