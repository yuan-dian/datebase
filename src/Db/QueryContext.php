<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

/**
 * Builder 对连接的最小依赖契约：表前缀 + 创建子查询。
 * Connection 实现本接口；BaseBuilder 只依赖本接口，与具体连接解耦（问题①）。
 */
interface QueryContext
{
    public function getTablePrefix(): string;

    public function newQuery(?string $table = null): BaseQuery;
}
