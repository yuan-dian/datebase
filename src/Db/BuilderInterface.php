<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\Db\Expression\Compiled;
use yuandian\Database\Db\Expression\QueryState;

/**
 * 统一 Builder 契约：把查询状态编译为可执行产物。
 * SQL 系与 Mongo 系各自实现；Connection::getBuilder() 返回本接口，消除联合类型。
 */
interface BuilderInterface
{
    public function compileSelect(QueryState $state): Compiled;

    public function compileInsert(string $table, array $data, ?string $comment = null): Compiled;

    public function compileInsertAll(string $table, array $dataList, ?string $comment = null): Compiled;

    public function compileUpdate(string $table, array $data, QueryState $state): Compiled;

    public function compileDelete(string $table, QueryState $state): Compiled;
}
