<?php
//error_reporting(0);
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
    private string $tablePre = 'bt_';
    public int $limit = 0;
    public int $zpage = 30; //默认最大页数
    public int $getInsertId = 0;
    public string $queryString = ''; //供外部调用查看的SQL串
    private string $user = 'daemon';
    private string $password = 'marlis123.';
    public function __construct()
    {
        $this->conn = pg_connect("host=localhost port=5432 dbname=btba user=$this->user password=$this->password");
    }

    /**
     * @param $query_string
     * @return resource|false 2021-11-21 09:22:00
     * 2021-11-21 09:22:00
     */
    public function query($query_string){
        if(!$this->conn){
            header("Content-Type: text/html; charset=UTF-8");
            exit('<div style="text-align: center;margin-top: 30px"><h1>很抱歉,网站升级中,请稍后访问...</h1><p>All Rights Reserved. BTBA. 2014-2025</p></div>');
        }

        $query = pg_query($this->conn,$query_string);
        if (!$query){
            echo $query_string."\n".pg_last_error($this->conn)."\n";
        }
        $this->queryString = $query_string.";\n";
        return $query;
    }

    /**
     * @param $query
     * @return array
     * 遇到json会自动转换成数组(对于数据库使用数组类型的,在字段处进行to_json转换)
     * 2021-11-21 09:24:59
     */
    public function fetch_row($query): array
    {
        if(!$query){
            exit('query error');
        }
        $arr = [];$not=[];

        while ($row = pg_fetch_object($query)){
            foreach ($row as $field=>$val){
                if($val){
                    $n = strlen($val)-1;
                    if($val[0]=='['&&$val[$n]==']'){
                        $row->$field = json_decode($val);
                    }
                }
            }
            $arr[] = $row;
            $ninField = $this->nin_field;
            if($ninField&&$row->$ninField){
                $not[] = $row->$ninField;
            }
        }


        if($not){
            $this->nin .= ($this->nin?',':'').implode(',',$not);
        }

        return $arr;
    }

    /**
     * @param array|object|string $where
     * @param array $char
     * @return string
     * 2021-11-26 12:22:24
     * update 2021-11-26 13:58:53
     */
    private function where(array|object|string|int $where,array $char=['where',' and']): string
    {
        if (is_array($where) || is_object($where)){
            $w= '';
            foreach ($where as $key => $val){
                $w .= ($w?$char[1]:$char[0])." $key=".(is_int($val)?$val:"'$val'");
            }
            $where = $w;
        }
        return $where;
    }

    /**
     * @param $table
     * @param string|array|object $where
     * @param string $field
     * @return array
     * 2021-11-21 09:25:06
     * update 2021-11-26 17:16:14
     */
    public function select($table, string|array|object $where='', string $field='*'): array
    {
        $where_from = $this->where($where);
        $limit = '';
        if ($this->limit){
            $page = min(get('page')?:1,$this->zpage);
            $skip = ($page - 1) * $this->limit;
            $limit = "limit $this->limit offset $skip";
            $this->selectCount($table,$where_from);
        }
        $query = $this->query("select $field from $this->tablePre$table $where_from $limit");
        return $this->fetch_row($query);
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
    public function selectCount($table, string $where=''): mixed
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
     * @param $table
     * @param array|object $data
     * @return int
     * 2021-11-24 10:08:10
     */
    public function insert($table,array|object $data): int
    {
        $data = (array)$this->pg_array($data);
        $keys = implode(', ', array_keys($data));
        $values = implode("', '", array_values($data));
        // 构建带有 RETURNING 的 SQL
        $result = $this->query("INSERT INTO $this->tablePre$table ($keys) VALUES ('$values') RETURNING id");
        if ($result) {
            $row = pg_fetch_assoc($result);
            $this->getInsertId = $row['id'];
        }
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
        return pg_affected_rows($result);
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
            $skip = ((get('page')?:1) - 1) * $this->limit;
            $limit = "limit $this->limit offset $skip";
            str_contains($where_form,'where')?$this->selectCount($table[0],$where_form):$this->select_id_seq($table[0]);
        }
        $query = $this->query("select $field from $this->tablePre$table[0] join $this->tablePre$table[1] using($using) $where_form $limit");
        return $this->fetch_row($query);
    }

    /**
     * 查询一条数据并返回
     * @param $table
     * @param string $where
     * @param string $field
     * @return mixed
     * 2021-11-27 20:11:25
     */
    public function select_one($table,string $where='',string $field='*'): mixed
    {
        $result = $this->select($table,$where.' limit 1',$field)[0];
        return str_contains($field,',')||$field=='*'?$result:$result->$field;
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
        $this->conn = pg_connect("host=localhost port=5432 dbname=btba user=$this->user password=$this->password");
        return (bool)$this->conn;
    }

    /**
     * @return bool
     * 2025-07-31 16:09:13
     * 用户数据库连接
     */
    public function user(): bool
    {
        $this->conn = pg_connect("host=localhost port=5432 dbname=btba_user user=$this->user password=$this->password");
        return (bool)$this->conn;
    }
}