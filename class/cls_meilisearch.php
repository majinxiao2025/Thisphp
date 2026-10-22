<?php
/**
 * Copyright © 2017-2025 Braveten Technology Co., Ltd.
 * Engineer: Makin
 * Date: 2025/7/28
 * Time: 15:05
 */

use Meilisearch\Client;

class cls_meilisearch
{
    private $index;
    /**
     * 索引 pagination.maxTotalHits 的值，超出这个位置的结果取不出来
     * 改这里之前先改索引设置，两边必须一致
     */
    private const MAX_TOTAL_HITS = 1000;

    /**
     * update 2026-10-22 整合自 btba_www
     * 连接参数移到 config.php 的 meilisearch 节点；前台只做检索，请配置只有 search 权限的 key，
     * 不要用 master key(它能改索引设置、能清空全部文档)
     */
    public function __construct(){
        require_once __DIR__. '/meilisearch/vendor/autoload.php';
        $conf = $GLOBALS['config']->meilisearch;
        $client = new Client($conf->url, $conf->key);
        $this->index = $client->index($conf->index);
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

    /*
     * index() 已删除 —— 2026-10-22 整合自 btba_www
     * 原方法里 updateDisplayedAttributes(...) 不是注释、会真正执行，谁调一次就会改掉线上索引的返回字段。
     * 索引设置不该由前台修改，应放在后台脚本里执行。常用操作速查：
     *   $idx = $this->index;
     *   print_r($idx->getSettings());                 // 查看全部当前设置(修改前先存一份方便回滚)
     *   $idx->updateSearchableAttributes([...]);      // 可搜字段；数组顺序 = 字段权重
     *   $idx->updateFilterableAttributes([...]);      // 可过滤字段
     *   $idx->updateDisplayedAttributes([...]);       // 返回哪些字段；["*"] = 全部
     *   $idx->updateSortableAttributes([...]);        // 可排序字段
     *   $idx->updatePagination(['maxTotalHits'=>N]);  // 改了必须同步改 MAX_TOTAL_HITS
     * 所有 update* 都是异步任务，且会触发全量重建索引。
     */

    /**
     * @param $query
     * @param int $limit
     * @return object
     * 2025-08-13 20:48:14 增加数据总数和每页数赋值
     * update 2026-10-22 修复分页(整合自 btba_www)
     * 原来用 limit/offset 检索，只能拿到 getEstimatedTotalHits()：一是对部分查询会高估几个数量级，
     * 二是它不受 maxTotalHits 约束，据此算出的页码翻过去是空页。
     * 改用分页检索拿 getTotalHits()，再用 real_total() 修正成真实可翻页数。
     */
    public function search($query,int $limit=10): object
    {
        $page = get('page')?:1;
        if(!is_numeric($page)){
            exit('Invalid page error');
        }
        $page = max(1,(int)$page);
        $response = $this->index->search($query, ['page'=>$page,'hitsPerPage'=>$limit,'filter'=>'display = 1']);
        $hits = $response->getHits();
        $total = $this->real_total((int)$response->getTotalHits(), count($hits), $page, $limit);
        $GLOBALS['limit'] = $limit;
        $GLOBALS['total'] = $total;
        return (object)[
            'hits'=>$hits,
            'total'=>$total,
            'query'=>$response->getQuery(),
        ];
    }

    /**
     * @param int $totalHits 检索接口报的总数
     * @param int $count 本页实际拿到的条数
     * @param int $page
     * @param int $limit
     * @return int 可翻页的真实总数
     * 2026-10-22 整合自 btba_www
     */
    private function real_total(int $totalHits, int $count, int $page, int $limit): int
    {
        //计数器不能超过 maxTotalHits，否则会渲染出翻不动的页码
        $cap = min($totalHits, self::MAX_TOTAL_HITS);
        //本页取满，后面还有没有取不到，只能信计数器
        if($count >= $limit){
            return $cap;
        }
        //本页有数据但没取满 —— 正常翻页的最后一页，能精确算出总数
        if($count > 0){
            return min(($page - 1) * $limit + $count, self::MAX_TOTAL_HITS);
        }
        //本页为空：第一页就空说明确实没有结果；否则是手输的越界页码
        return $page > 1 ? min($cap, ($page - 1) * $limit) : 0;
    }

    /**
     * @return array
     * 清空数据
     * 2025-07-31 11:36:42
     * 2026-10-22 前台不应具备清库能力，默认注释掉(整合自 btba_www)；确需使用请在后台用 master key 调用
     */
    /*public function clear(): array
    {
        return $this->index->deleteAllDocuments();
    }*/
}
