<?php

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;

/**
 * Class Mongodb
 * 2020-10-30 00:20
 * update 2021-07-21 12:14:17
 * update 2021-11-07 16:01:00
 */
class cls_Mongodb
{
    private string $url = 'mongodb://127.0.0.1:27017';
    private string $db = 'index';
    private string $tablePre = 'in_';

    private Manager $mongodb;
    public int $limitNum = 0;
    public bool $limitPage = false;
    public function __construct(){
        $this->mongodb = new Manager($this->url);
    }
    /**
     * 表名称组装
     * @param $tableName
     * @return string|void
     */
    private function table($tableName){
        return $tableName?$this->db.'.'.$this->tablePre.$tableName:exit('table name is null');
    }
    /**
     * @param $table
     * @param $document object|array
     * @return int|null
     */
    public function insert($table, object|array $document): ?int
    {
        $bulk = new BulkWrite;
        $id = $bulk->insert($document);
        $writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000);
        $result = $this->mongodb->executeBulkWrite($this->table($table), $bulk,$writeConcern);
        return $result->getInsertedCount();
    }
    /**
     * @param $table
     * @param array $fields 只需要返回的字段；title=>1,不需要某个字段title=0,只能同时设置1或者0
     * @param object|array $filter
     * @param array $options
     * @return array
     * @throws \MongoDB\Driver\Exception\Exception
     * 2021-04-08 16:53:33 记录因为查询的数据类型不一致，无法获取到数据
     * 2021-04-12 09:53:44 将$fields参数放在了第三位
     * Update 2021-11-05 20:19:17
     */
    public function select($table, object|array $filter=array(), array $fields=[], array $options=array('sort'=>['updated'=>-1])): array
    {
        $arr = [];
        if($filter['_id']){$filter['_id']=$this->_id($filter['_id']);}
        //通常情况下不返回_id
        $fields['_id']=$fields['_id']?:0;

        if(!$fields['_id']&&count($fields)>0){
            $options['projection']=$fields;
        }
        if($this->limitNum){
            $skip = ((get('page')?:1) - 1) * $this->limitNum;
            $options['skip']=$skip;$options['limit']=$this->limitNum;
            $GLOBALS['limitNum'] = $this->limitNum;
        }
        if($this->limitPage){
            $cursor = $this->command($table,$filter);
            $GLOBALS['total'] = current($cursor)->n;
        }
        $query = new Query($filter, $options);
        $cursor = $this->mongodb->executeQuery($this->table($table), $query);
        foreach ($cursor as $body){
            if($body->_id){
                $_id =(array)$body->_id;
                $body->_id = $_id['oid'];
            }
            $arr[] = $body;
        }
        return $arr;
    }

    /**
     * @param $table
     * @param object|array $filter
     * @param object|array $data
     * @param bool[] $options
     * @return int|null
     *
     * 2021-04-12 10:47:40
     * 用$push追加数据到数组中，非整行更新；例如：$upData = array('$push'=>['field'=>'value']);
     * 用$set追加数据到字段中，非整行更新；例如 $set=>['field'=>'value']
     * update: 2021-10-21 18:31:58
     */
    public function update($table, object|array $filter=[], object|array $data=[], array $options=array('multiple'=>true,'upsert'=>true)): ?int
    {
        if($filter['_id']){$filter['_id']=$this->_id($filter['_id']);}
        if($data->_id)unset($data->_id);if($data->id)unset($data->id);
        $bulk = new BulkWrite;
        $bulk->update($filter,$data,$options);
        $writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000);
        $result = $this->mongodb->executeBulkWrite($this->table($table), $bulk, $writeConcern);
        return $result->getModifiedCount();
    }

    /**
     * @param $table
     * @param object|array $filter
     * @param bool[] $options
     * @return int|null
     */
    public function delete($table, object|array $filter=[], array $options=['multiple'=>true]): ?int
    {
        if($filter['_id']){$filter['_id']=$this->_id($filter['_id']);}
        $bulk = new BulkWrite;
        $bulk->delete($filter,$options);
        $writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000);
        $result = $this->mongodb->executeBulkWrite($this->table($table), $bulk, $writeConcern);
        return $result->getDeletedCount();
    }

    /**
     * 不带$id参数 可以生成新的_id戳
     * @param string|null $id
     * @return ObjectID
     * 2021-07-19 07:16:33
     */
    public function _id(string $id=NULL): ObjectID
    {
        return new ObjectID($id);
    }

    /**
     * 命令调用
     * @param string $table
     * @param array|object $filter
     * @return array
     * @throws \MongoDB\Driver\Exception\Exception
     */
    private function command(string $table, array|object $filter): array
    {
        $cmd =  new Command([
            'aggregate' => $this->tablePre.$table,
            'pipeline' => [
                ['$match' => (object) $filter],
                ['$sort'=>['did'=>1]],
                [
                    '$group' => ['_id' => 1, 'n' => ['$sum' => 1]]
                ]
            ],
            'cursor' => new stdClass,
        ]);
        //$cmd =new Command(["count"=>$this->tablePre.$table,"query"=>$filter]);
        return $this->mongodb->executeReadCommand($this->db,$cmd)->toArray();
    }

    /**
     * 创建索引
     * 2020-11-04 15:34:41 没有完成
     *
     */
    public function creatIndex(){
        $cursor = $this->command(['createIndex']);
        foreach ($cursor as $document) {
            var_dump($document);
        }
    }

}