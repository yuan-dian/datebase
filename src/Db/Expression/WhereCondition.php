<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Expression;

use Closure;

/**
 * 单条查询条件。value 显式联合，四种形态：
 * - 标量/null/数组：普通比较值（parseCompare/parseLike/parseIn...）
 * - Raw：原生 SQL 片段
 * - WhereGroup：嵌套条件组（whereGroup），构建期求值
 * - Closure：子查询构造器（where('id', '=', fn($q) => ...)），解析期由 Builder 调用
 */
final class WhereCondition
{
    public function __construct(
        public readonly string $field,
        public readonly string $operator,
        public readonly string|int|float|bool|null|array|Raw|Closure|WhereGroup $value,
    ) {}
}
