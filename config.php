<?php
/**
 * Copyright © 2017-2025 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2020/11/22
 * Time: 3:31 下午
 */
return (object)[
    'version'=>'1.0',
    //视图文件扩展名
    'view_ext'=>'',//.min.php
    'page404'=>'404.html',
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
        'index'=>'https://www.x.com',
    ],
    'info'=>(object)array(
        'sitename'=>'站点名称'
    )
];