<?php

/**
 * Copyright © 2017-2025 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2025/7/28
 * Time: 15:05
 */

use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;

class cls_meilisearch
{
    private $index;
    /**
     * @return void
     * 操作范例
     */
    public function __construct(){
        require_once __DIR__. '/meilisearch/vendor/autoload.php';
        $client = new Client('http://127.0.0.1:7700', 'At43V9WVh0yRI_piRNdNs4aTJZHnuWLm74IPV-PtahE');
        $this->index = $client->index('index_db');
    }

    /**
     * @param $json
     * @return mixed 2025-08-10 16:07:04
     * 2025-08-10 16:07:04
     * 添加数据
     */
    public function add($json): mixed
    {
        return $this->index->addDocuments($json);
    }
    public function index()
    {
        //$fields=[ 'did','tags','title', 'image','director','stars','country',''];

        echo '<pre>';

        /*//修改最大搜索返回结果maxTotalHits为10万
        $index->updateSettings(['pagination' => [
                'maxTotalHits' => 10000
            ]
        ]);*/
        //获取设置
        //$get = $index->getSettings();
        //print_r($get);
        //禁止id被索引
        //displayedAttributes
        //['did,title,image,director,stars,country,score,showdate,text,updated']
        $get = $this->index->updateDisplayedAttributes(['did','title','image','director','stars','country','score','showdate','torrents','update']);
        //$get = $this->index->getDisplayedAttributes();
        print_r($get);
        //对想要的字段进行排序
        //$res=$index->updateSortableAttributes(['id','update']);
        //print_r($res);exit();
        //
        /*$index->updateFilterableAttributes([
            'display',[
                'attributePatterns'=>['tags','stars','director','country'],
                'features' => [
                    'facetSearch' => true,
                    'filter' => [
                        'equality' => true,
                        'comparison' => true,
                    ],
                ]
            ]
        ]);*/
        //$get = $index->getFilterableAttributes();
        //获取可过滤的属性

        //$get = $client->index('btba')->getFilterableAttributes();
        //$get=$index->search('', ['sort' => ['id:desc'],'limit' => 15,'filter'=>'display = 1']);

        //$get->getHit();
        //$get=$index->getDocuments((new DocumentsQuery())->setFilter(['display = 1'])->setLimit(5));
        //$get = $index->getDocuments();
        //var_dump($get);
        //print_r($get);
        echo '</pre>';

       /* $documents = [
            ['id' => 1,  'title' => 'Carol', 'genres' => ['Romance, Drama']],
            ['id' => 2,  'title' => 'Wonder Woman', 'genres' => ['Action, Adventure']],
            ['id' => 3,  'title' => 'Life of Pi', 'genres' => ['Adventure, Drama']],
            ['id' => 4,  'title' => 'Mad Max: Fury Road', 'genres' => ['Adventure, Science Fiction']],
            ['id' => 5,  'title' => 'Moana', 'genres' => ['Fantasy, Action']],
            ['id' => 6,  'title' => 'Philadelphia', 'genres' => ['Drama']],
        ];

# If the index 'movies' does not exist, Meilisearch creates it when you first add the documents.
        $index->addDocuments($documents); // => { "uid": 0 }*/
    }

    /**
     * @param $query
     * @param int $limit
     * @return object
     * 2025-08-13 20:48:14 增加数据总数和每页数赋值
     */
    public function search($query,int $limit=10): object
    {
        $page = get('page')?:1;
        if(!is_numeric($page)){
            exit('Invalid page error');
        }
        $response = $this->index->search($query, ['limit' => $limit,'offset'=>$limit * ($page - 1),'filter'=>'display = 1']);//
        $GLOBALS['limit'] = $limit;
        $GLOBALS['total'] = $response->getEstimatedTotalHits();
        return (object)[
            'hits'=>$response->getHits(),
            'total'=>$response->getEstimatedTotalHits(),
            'query'=>$response->getQuery(),
        ];
    }

    /**
     * @return array
     * 清空数据
     * 2025-07-31 11:36:42
     */
    public function clear(): array
    {
        return $this->index->deleteAllDocuments();
    }
}