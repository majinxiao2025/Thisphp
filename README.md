# 重要的事说在前面
### 支持最新版本 PHP8.5 ，最新版 Apache httpd, 不在支持 php5.6
### 最简单最高效的 PHP API 框架
本框架简单高效，适合任何站点，适合任何APP接口等，并在500强公司的项目上已经实践使用过
此框架准许MIT开放标准，
如果对此框架有任何建议修改，请直接分叉，酌情合并供大家使用

#### controller控制器目录
下新建控制文件以clr_ 开头
clr_index.php

#### class 公用类目录:
新建类文件以cls_ 开头
cls_index.php

#### view视图文件夹下新建文件方式为:
版本目录/控制目录/视图文件
1.0/index/index.php
如果项目维护或需要升级版本
则可在 view目录下新建个2.0目录或者其它名称，然后在配置文件中config.php中修改，即可丝滑升级

#### 框架核心文件
- `/index.php` 主引导文件
- `/core.php` 主内核
- `/controller.php` 主控制器
- `/config.php` 全局配置
- `/.htaccess` apache伪静态文件
- `/view` 视图文件夹，存储html、php 等
- `/class` 公用类；cls_开头
- `/controller` 控制器文件夹，下面新建以 clr_开头的文件

### 使用方式
直接将框架上传到站点目录，配置apache 或者nignx 访问网站会看到Hello world

新增访问目录或者页面
请在controller 下新建类
比如你要访问/item 目录
则
add:
`controller/clr_item.php`
在clr_item.php内容为：

```
class clr_item extends controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function index()
    {
        $this->view();
    }
}
```
在view下新增php html页面
比如当前是1.0版本
则添加文件view/1.0/item/index.php
内容就是php html内容,参照view/1.0/index/index.php

