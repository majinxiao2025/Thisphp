# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 概述

Thisphp 是一个极简、无第三方依赖的 PHP MVC/API 框架（面向 PHP 8.5 和 Apache httpd；不再支持 PHP 5.6）。项目没有 Composer、构建步骤、lint 配置或测试套件。README 和代码注释均为中文。

唯一可用的检查是语法检查：

```sh
php -l path/to/file.php
```

框架直接部署到 Apache（或 nginx）站点根目录即可运行；路由依赖 `.htaccess` 伪静态规则，所以单独使用 `php -S` 无法正确路由。在没有伪静态的情况下，可以直接访问 `index.php?class=<名称>&method=<名称>`。

## 请求流程

1. `.htaccess` 将 `/` 重写为 `class=index&method=index`，`/foo` 重写为 `class=foo&method=index`，`/foo/bar` 重写为 `class=foo&method=bar`（class 段匹配 `[a-z_]{1,20}`，method 段匹配 `[a-zA-Z0-9_]{2,20}`）。
2. `index.php` 将 `config.php` 加载到 `$GLOBALS['config']`（是一个对象，通过 `$GLOBALS['config']->version`、`$this->config->...` 访问），用正则白名单校验 `class`/`method` 后存入 `$GLOBALS['route']`，定位 `controller/clr_<class>.php`（不存在则跳转到 `/`），实例化 `clr_<class>` 并调用 `<method>()`。
   - 只能路由到控制器**自身声明的** public 非静态方法；`__*` 魔术方法和继承来的 `view`/`page`/`not404` 都不可通过 URL 调用，不满足时走 `not404()`（真正的 404 状态码）。
   - `?page=` 在入口统一校验：只接受正整数，空值视为未传，其他一律 404。
   - 视图路径必须用 `$GLOBALS['route']`，不要用 `$_REQUEST['class']`（POST/COOKIE 可覆盖，会造成任意文件包含）。
3. 类继承关系：`core`（`core.php`：视图、404、`RandInt`）→ `controller`（`controller.php`：公共辅助方法）→ `clr_*` 控制器。
4. `controller::__construct()` 会设置 `$this->data = new stdClass()` 和 `$this->config`，并且**每个请求都会通过 `$this->db = inClass('postgresql')` 建立 PostgreSQL 连接**，同时回填 `$this->db->owner = $this`（翻页越界时 DB 层借它调 `not404()`）。子类必须调用 `parent::__construct()`。

## 命名约定（由加载器强制要求，不只是风格）

- 控制器：`controller/clr_<名称>.php`，内容为 `class clr_<名称> extends controller`。
- 公用类：`class/cls_<名称>.php`，内容为 `class cls_<名称>`；通过 `inClass('<名称>', $params)` 加载（include 后 `new`）。
- 控制器之间互相调用：`inController('<名称>')`。
- 视图：`view/<config 中的 version>/<class>/<method><view_ext>.php`。`$this->view()` 不传参数时使用当前路由；`$this->view('path/file')` 传入相对于版本目录的路径（不带 `.php`）。视图是在控制器内部 `include` 的，所以可以直接读取 `$this->data`。找不到视图时以 404 状态码回退到 `view/<version>/404.html`。
- 版本控制：修改 `config.php` 中的 `version`，并新建 `view/<新版本>/` 目录，即可切换到一套新视图。

## 全局辅助函数（定义在 `index.php`）

- `get($name)` / `post($name)` 读取 `$_GET` / `$_POST`；不传参数时以对象形式返回整个数组（`get()` 会去掉 `class`/`method`）。传入第二个参数则为设置值。
- `json()` 解析请求体中的原始 JSON 数据。

## 控制器辅助方法（`controller.php`）

`json()` / `echo_json()` 输出 `{"result":"success","msg":...,"data":...}`（`echo_json` 会设置 JSON Content-Type）；`bejson()` 返回 JSONP 风格的 `(...)`；`page()` 根据 `$GLOBALS['total']` / `$GLOBALS['limit']` 生成分页 `<li>` HTML；另有 `location()`、`not404()`（在 `core` 中）。

- `token()`/`deToken()`/`tokenPath()` 只是可逆编码（字母表在源码里），不能用于防伪造；登录态 cookie 用 `signUid()`/`verifyUid()`（HMAC，依赖 `config->authSecret`，为空时 `signUid` 直接退出、`verifyUid` 返回 null）。
- `getIp()` 只有在 `REMOTE_ADDR` 属于 `config->trusted_proxies` 时才采信 `X-Forwarded-For`。
- `asset($path)`：`config->https->cdn` + 路径 + `?v=filemtime`（本地文件位于 `external/static/`）。
- `siteLink($url)`：按 `config->siteRoots` 白名单把自家根域换成当前访问的根域（多域名站点）；`config.php` 顶部按 Host 白名单派生 `siteRoot`，不要直接使用 `HTTP_HOST`。
- `RandInt()` 使用 `random_int`，可用于验证码等场景。
- 验证码：`issueMailCode($key,$target)` 签发并记下发送目标，`checkMailCode($key,$target,$code,$consume)` 校验（绑定目标、10 分钟有效、错 5 次作废、默认用后作废）。不要用 `$code == $_SESSION[...]` 直接比较——没发过验证码时两边都是 null。
- `isSiteUrl($url)`：用户传入的跳转地址必须先过它（只允许相对路径和 `config->siteRoots` 白名单域名）。
- `rateLimited($key,$max,$windowSec)`：按 IP 限流，依赖 `cls_redis`（Redis 连不上时 `cls_redis` 会直接 exit）。

## 数据库层（`class/cls_postgresql.php`）

主要的数据库封装类。连接参数硬编码在类中（`localhost:5432`，数据库 `btba`），使用 `pg_pconnect` 复用连接，每次连接后执行一次 `ROLLBACK` 清理残留事务。`user()` 切到 `btba_user`，`master()` 切回主库，`switchDb($db)` 是统一入口（已在目标库则不切），`$currentDb` 记录当前库。所有表名会自动加上 `$tablePre = 'bt_'` 前缀——传入时不要带前缀。

- `select($table, $where, $field)`（结果经 `fetch_object()`）、`select_one(...)`（当 `$field` 为单个字段时直接返回该值，查不到返回 null）、`insert`（通过 `RETURNING id` 设置 `$getInsertId`）、`update`、`delete`、`select_join`（结果经 `fetch_row()`）、`select_count`（别名 `selectCount`）。
- `$where` / insert / update 的**数组或对象形式**是安全的：字段名必须匹配 `^[a-zA-Z_][a-zA-Z0-9_]*$`（否则退出），值经 `pg_escape_literal` 转义（int 原样输出）。**字符串形式**的 `$where` 原样拼接，绝不能拼入用户输入。`$table`/`$field` 参数也是原样拼接。
- 分页：在 `select()`/`select_join()` 之前设置 `$this->db->limit = N`；先执行 count 写入 `$GLOBALS['total']`/`$GLOBALS['limit']` 并把 `limit` 重置为 0，再由 `checkPage()` 校验页码，超出实际页数（封顶 `$zpage`）直接 404。`controller::page()` 使用这些全局变量。
- insert/update 数据中的 PHP 数组会被 `pg_array()` 转换为 PG 数组字面量（`{...}`）；`fetch_row()` 会自动解码结果中看起来像 JSON 的字符串。
- 查询失败时只写 `error_log`（不输出给访客）；`$queryString` 保存最后一次执行的 SQL。

`class/` 下的其他封装（MongoDB、MySQLi、Redis、Meilisearch、cURL、PHPMailer、拼音、验证码 `cls_verCode`）都是可选的，只在通过 `inClass()` 加载时使用，连接参数大多硬编码在各自类中（Meilisearch 读取 `config->meilisearch`，应配置 search-only key）。

- `cls_curl` 默认校验 HTTPS 证书（`$verify_ssl = false` 可关闭），且限制只能使用 http/https 协议；cookie jar 存放在系统临时目录，不在网站目录下。
- `cls_verCode::captcha()` 输出图片验证码，`checkCaptcha($input)` 校验（一次性、5 分钟有效）。

## 部署注意

`.htaccess` 会拒绝访问隐藏文件（`.git`、`.idea` 等，`.well-known` 除外）和除 `index.php` 以外的所有 `.php` 文件。使用 nginx 时需要配置等效规则。
