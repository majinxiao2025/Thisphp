<?php
//error_reporting(0);
use PgSql\Result;

/**
 * Copyright © 2017-2021 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2021/11/21
 * Time: 09:07
 * update 2021-11-26 17:19:41
 */
class cls_postgresql
{
    public \PgSql\Connection|false $conn;
    public string $nin_field='';
    public string $nin=''; //供not in 所使用的值
    public string $tablePre = 'bt_';
    public int $limit = 0;
    public int $zpage = 30; //默认最大页数
    public int $getInsertId = 0;
    public string $queryString = ''; //供外部调用查看的SQL串
    //当前连的是哪个库(master()/user()/switchDb() 会更新它)，被别处调用的共用逻辑可以先记下原来的库，用完再切回去
    public string $currentDb = 'btba';
    //创建它的 controller 实例(controller::__construct() 回填)，翻页超出范围时借它调 not404()
    public mixed $owner = null;
    private string $user = 'daemon';
    private string $password = '';
    public function __construct()
    {
        $this->connect('btba');
    }

    /**
     * 连接指定数据库
     * pg_pconnect 在同一个 PHP-FPM 进程内复用底层连接，切库/每次请求不必重新握手；
     * 复用的连接可能残留上个请求没提交的事务，ROLLBACK 一次兜底(没有事务时是空操作)
     * @param string $db
     * @return bool
     * 2026-10-22 整合自 btba_www
     */
    private function connect(string $db): bool
    {
        $this->conn = pg_pconnect("host=localhost port=5432 dbname=$db user=$this->user password=$this->password");
        if($this->conn){
            pg_query($this->conn,"ROLLBACK");
        }
        $this->currentDb = $db;
        return (bool)$this->conn;
    }

    /**
     * @param $query_string
     * @return Result
     */
    public function query($query_string):PgSql\Result|false
    {
        $this->checkConn();
        $query = pg_query($this->conn,$query_string);
        if (!$query){
            //写入错误日志，不输出给浏览器，避免泄露SQL和表结构 2026-10-22
            error_log('[pg] '.pg_last_error($this->conn).' | SQL: '.$query_string.' | URI: '.($_SERVER['REQUEST_URI'] ?? ''));
        }
        $this->queryString = $query_string.";\n";
        return $query;
    }
    /**
     * @param $query
     * @return array
     * 遇到json会自动转换成数组(对于数据库使用数组类型的,在字段处进行to_json转换)
     * 2021-11-21 09:24:59
     * 2026-02-05 16:05:07 标记可能要废弃!
     * 2026-05-27 19:55:37 改良数据值有[]误认为json字符串
     */
    public function fetch_row($query): array
    {
        if(!$query){
            exit('query error');
        }
        $arr = [];
        $not = [];

        while ($row = pg_fetch_object($query)){
            foreach ($row as $field => $val){
                // 改进1：更严格的空值检查
                if($val === null || $val === ''){
                    continue;
                }
                // 改进2：只对看起来像JSON的字符串进行解析
                if(is_string($val) && strlen($val) >= 2){
                    $firstChar = $val[0];
                    $lastChar = $val[strlen($val) - 1];

                    // 真正的JSON格式：{"key":"value"} 或 ["a","b"]
                    if(($firstChar === '{' && $lastChar === '}') ||
                        ($firstChar === '[' && $lastChar === ']')){
                        $decoded = json_decode($val);
                        if($decoded !== null && json_last_error() === JSON_ERROR_NONE){
                            $row->$field = $decoded;
                        }
                        // 如果JSON解析失败，保持原值不变
                    }
                    // [字符串] 这种格式不会被处理，保持原样
                }
            }
            $arr[] = $row;

            $ninField = $this->nin_field;
            // 改进3：使用 isset 避免未定义属性警告
            if($ninField && isset($row->$ninField) && $row->$ninField){
                $not[] = $row->$ninField;
            }
        }

        if($not){
            $this->nin .= ($this->nin ? ',' : '') . implode(',', $not);
        }

        return $arr;
    }
    /**
     * @param $query
     * @return array
     * 2026-02-05 15:27:04
     * 查询并返回数组对象
     */
    public function fetch_object($query): array
    {
        $ary = $not = [];
        while ($row = pg_fetch_object($query)) {
            $arr = [];
            foreach ($row as $field => $val) {
                #如果遇到输出{}开头和结尾的数据强制解析成数组
                if ($val[0] == '{' && $val[-1] == '}') {
                    $val = trim($val, '{}');
                    $val = explode(',', $val);
                } else if ($val[0] == '[' && $val[-1] == ']') {
                    $val = json_decode($val);
                }
                $arr[$field] = $val;
            }
            $ary[] = (object)$arr;
            $ninField = $this->nin_field;
            if($ninField&&$arr[$ninField]){
                $not[] = $arr[$ninField];
            }
        }
        if($not){
            $this->nin .= ($this->nin?',':'').implode(',',$not);
        }
        return $ary;
    }

    public function nin_field($nin_field)
    {

    }


    /**
     * @param array|object|string|int $where
     * @param array $char
     * @return string
     * 2021-11-26 12:22:24
     * update 2021-11-26 13:58:53
     */
    private function where(array|object|string|int $where,array $char=['where',' and']): string
    {
        //注意：字符串形式的 $where 原样拼接，不能直接拼入用户输入，请使用数组/对象形式
        if (is_array($where) || is_object($where)){
            $w= '';
            foreach ($where as $key => $val){
                $w .= ($w?$char[1]:$char[0])." ".$this->field($key)."=".$this->literal($val);
            }
            $where = $w;
        }
        return $where;
    }

    /**
     * 数据库连接检查
     * 2026-10-22
     */
    private function checkConn(): void
    {
        if(!$this->conn){
            header("Content-Type: text/html; charset=UTF-8");
            exit('<div style="text-align: center;margin-top: 30px"><h1>很抱歉,网站升级中,请稍后访问...</h1><p>All Rights Reserved. BTBA. 2014-2025</p></div>');
        }
    }

    /**
     * 字段名校验，防止通过字段名(如直接传入 post())注入
     * @param string|int $key
     * @return string
     * 2026-10-22
     */
    private function field(string|int $key): string
    {
        if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/',(string)$key)){
            error_log("cls_postgresql: invalid field name: $key");
            exit('invalid field');
        }
        return (string)$key;
    }

    /**
     * 值转义，整数原样输出，其它统一用 pg_escape_literal 转义
     * @param mixed $val
     * @return string
     * 2026-10-22
     */
    private function literal(mixed $val): string
    {
        if(is_int($val))return (string)$val;
        $this->checkConn();
        return pg_escape_literal($this->conn,(string)$val);
    }

    /**
     * 查询一条数据并返回
     * @param $table
     * @param string|array|object $where
     * @param string $field
     * @return mixed
     * 2021-11-27 20:11:25
     * update 2026-04-20 23:14:53
     * update 2026-10-22 查不到记录时直接返回 null，不再产生 Undefined array key / read property on null 警告
     */
    public function select_one($table,string|array|object $where='',string $field='*'): mixed
    {
        $where_from = $this->where($where);
        $result = $this->select($table,$where_from.' limit 1',$field)[0] ?? null;
        if($result===null){
            return null;
        }
        return str_contains($field,',')||$field=='*'?$result:$result->$field;
    }
    /**
     * @param $table
     * @param string|array|object $where
     * @param string $field
     * @return array
     * 2021-11-21 09:25:06
     * update 2021-11-26 17:16:14
     * update 2026-02-05 15:28:48
     */
    public function select($table, string|array|object $where='', string $field='*'): array
    {
        $where_from = $this->where($where);
        $limit = '';
        if ($this->limit){
            $limitNum = $this->limit;
            $this->select_count($table,$where_from); //会把 total/limit 写进 $GLOBALS，并把 $this->limit 恢复为 0
            $page = $this->checkPage($limitNum);
            $skip = ($page - 1) * $limitNum;
            $limit = "limit $limitNum offset $skip";
        }
        $query = $this->query("select $field from $this->tablePre$table $where_from $limit");
        return $this->fetch_object($query);
    }

    /**
     * 校验 ?page= 是否超出这次查询实际的最后一页(total/limit，封顶 zpage)，超出直接 404
     * select()/select_join() 共用
     * @param int $limitNum
     * @return int
     * 2026-10-22 整合自 btba_www
     */
    private function checkPage(int $limitNum): int
    {
        $page = max(1,(int)get('page')); //入口 index.php 已校验过只会是正整数，这里再兜一层
        $zPage = !empty($GLOBALS['total']) ? (int)ceil($GLOBALS['total']/$limitNum) : $this->zpage;
        $zPage = min($zPage, $this->zpage);
        if($page > max(1,$zPage)){
            $this->pageNotFound();
        }
        return $page;
    }

    /**
     * 翻页超出范围时走 404：优先借 $owner(创建本实例的 controller)的 not404() 渲染 404 模板，
     * 模板里可能用到 controller 的方法，不能在这里直接 include
     * 2026-10-22
     */
    private function pageNotFound(): void
    {
        if($this->owner && method_exists($this->owner,'not404')){
            $this->owner->not404();
        }
        header("HTTP/1.0 404 Not Found");
        exit('ERROR 404 !');
    }

    /**
     * @param $table
     * @param array $array
     * @param bool|int $flags
     * @param int $mode
     * @return mixed
     * 2021-11-24 10:08:14
     */
    public function pg_select($table,array $array, bool|int $flags=PGSQL_DML_EXEC, int $mode= PGSQL_ASSOC): mixed
    {
        return pg_select($this->conn,$this->tablePre.$table,$array,$flags,$mode);
    }

    /**
     * @param $table
     * @param string $where
     * update 2021-11-26 17:16:05
     * @return mixed
     */
    public function select_count($table, string $where=''): mixed
    {
        if(str_contains($where, 'order by')){
            $where = preg_replace('/order by.*[desc|asc]/','',$where);
        }

        if(str_contains($where, 'limit')) {
            $where = preg_replace('/limit.*/', '', $where);
        }
        $query = $this->query("select count(*) from $this->tablePre$table $where");
        $GLOBALS['total'] = pg_fetch_object($query)->count;
        $GLOBALS['limit'] = $this->limit;
        $this->limit = 0; //恢复
        return $GLOBALS['total'];
    }

    /**
     * select_count 的别名，兼容 btba_www 等项目里的 selectCount() 写法
     * 2026-10-22
     */
    public function selectCount($table, string $where=''): mixed
    {
        return $this->select_count($table,$where);
    }

    /**
     * @param $table
     * @param array|object $data
     * @return int
     * 2021-11-24 10:08:10
     */
    public function insert($table,array|object $data): int
    {
        $data = (array)$this->pg_array($data);
        $keys = implode(', ', array_map([$this,'field'],array_keys($data)));
        //每个值单独转义 2026-10-22
        $values = implode(", ", array_map(fn($val)=>$this->literal($val),array_values($data)));
        // 构建带有 RETURNING 的 SQL
        $result = $this->query("INSERT INTO $this->tablePre$table ($keys) VALUES ($values) RETURNING id");
        if (!$result) return 0;
        $row = pg_fetch_assoc($result);
        $this->getInsertId = $row['id'];
        return pg_affected_rows($result);
    }
    public function update($table,array|object|string $where,array|object|string $data): int
    {
        $where_form = $this->where($where);
        $data_form = $this->where($this->pg_array($data),['',',']);
        $result = $this->query("update $this->tablePre$table set $data_form $where_form");
        return $result?pg_affected_rows($result):0;
    }
    /**
     * @param $table
     * @param array|string|object $where
     * @return int
     * 2021-11-26 12:28:46
     */
    public function delete($table,array|string|object $where): int
    {
        $where_from = $this->where($where);
        $result = $this->query("delete from $this->tablePre$table $where_from");
        return $result?pg_affected_rows($result):0;
    }

    public function select_id_seq($table){
        $query = $this->query("select last_value from ".$this->tablePre.$table."_id_seq");
        $GLOBALS['total'] = pg_fetch_object($query)->last_value;
        $GLOBALS['limit'] = $this->limit;
        $this->limit = 0; //恢复
    }

    /**
     * 关联表查询
     * @param array $table
     * @param string $using
     * @param array|object|string $where
     * @param string $field
     * @return array
     * 2021-11-27 10:55:42
     */
    public function select_join(array $table=[],string $using='id',array|object|string $where='',string $field='*'): array
    {
        $where_form = $this->where($where);
        $limit = '';
        if ($this->limit){
            $limitNum = $this->limit;
            str_contains($where_form,'where')?$this->select_count($table[0],$where_form):$this->select_id_seq($table[0]);
            $page = $this->checkPage($limitNum);
            $skip = ($page - 1) * $limitNum;
            $limit = "limit $limitNum offset $skip";
        }
        $query = $this->query("select $field from $this->tablePre$table[0] LEFT JOIN $this->tablePre$table[1] using($using) $where_form $limit");
        return $this->fetch_row($query);
    }

    /**
     * PHP Array to PGSQL Array
     * @param array|object|string $data
     * @return object|string 2021-12-01 00:16:40
     * 2021-12-01 00:16:40
     */
    private function pg_array(array|object|string $data): object|string
    {
        if(!is_array($data)&&!is_object($data)){
            return $data;
        }
        $data = (array)$data;
        foreach ($data as $field=>$val){
            //如果是数组
            if(is_array($val)){
                $json = json_encode($val,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $str=substr_replace($json,'{',0,1);
                $data[$field] = substr_replace($str,'}',-1,1);
            }
        }
        return (object)$data;
    }

    /**
     * 主库连接
     * @return bool
     * 2021-12-01 23:14:57
     */
    public function master(): bool
    {
        return $this->connect('btba');
    }
    /**
     * @return bool
     * 2025-07-31 16:09:13
     * 用户数据库连接
     */
    public function user(): bool
    {
        return $this->connect('btba_user');
    }

    /**
     * master()/user() 的统一入口：按库名切换，已在目标库上就不重复切
     * 给"不确定调用方原来连的是哪个库、用完还要切回去"的共用逻辑使用
     * @param string $db 'btba'|'btba_user'
     * @return bool
     * 2026-10-22 整合自 btba_www
     */
    public function switchDb(string $db): bool
    {
        if($this->currentDb === $db){
            return (bool)$this->conn;
        }
        return $db === 'btba_user' ? $this->user() : $this->master();
    }
}