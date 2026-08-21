<?php

declare(strict_types=1);

namespace yuandian\Database\Db\State;

use yuandian\Database\Db\Raw;

/**
 * 类型化查询状态，替代 BaseQuery::$options 裸数组。
 * 可变对象（链式方法原地修改），newSubQuery/chunk 用 copy() 深拷贝 where 组。
 */
final class QueryState
{
    public string $table = '';
    public string $alias = '';
    /** @var array<int|string, string|Raw> field 列表或 原名=>别名 */
    public array $field = ['*'];
    public WhereGroup $where;
    /** @var list<array{0:string,1:string}> */
    public array $order = [];
    public ?int $limit = null;
    public ?int $offset = null;
    /** @var list<array{type:string,table:string|array,condition:string}> */
    public array $join = [];
    /** @var list<string> */
    public array $group = [];
    /** @var list<string> */
    public array $having = [];
    public bool|string $lock = false;
    /** @var list<array{type:string,query:BaseQuery|string}> */
    public array $union = [];
    public string $comment = '';
    public bool $distinct = false;
    public string|array|false $forceIndex = '';
    /** @var array<string, mixed> update/inc 数据（Express/Raw/标量） */
    public array $data = [];
    /** 驱动专属选项桶（typeMap/batchSize/collation 等） */
    public array $extra = [];

    public function __construct()
    {
        $this->where = new WhereGroup();
    }

    public function copy(): self
    {
        $new = clone $this;
        $new->where = new WhereGroup();
        $new->where->and = $this->where->and;
        $new->where->or = $this->where->or;
        return $new;
    }
}
