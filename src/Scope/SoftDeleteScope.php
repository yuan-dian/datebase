<?php

declare(strict_types=1);

namespace yuandian\Database\Scope;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Db\Expression\WhereCondition;

/**
 * 内置软删除全局作用域
 *
 * 根据 #[SoftDelete] 的 $default 属性决定查询条件：
 *  - $default = null  → WHERE column IS NULL（排除已删行）
 *  - $default ≠ null  → WHERE column = $default（排除已删行）
 */
class SoftDeleteScope implements Scope
{
    public function apply(BaseQuery $query, string $modelClass): void
    {
        $softDelete = $modelClass::getSoftDelete();
        if (!$softDelete || !$softDelete->active()) {
            return;
        }

        $column = $softDelete->column;
        $default = $softDelete->default;

        if ($default === null) {
            // $default 为 null：已删行的 column 有值，未删行为 null
            if (!$query->getState()->where->hasCondition($column, 'NULL')) {
                $query->getState()->where->add('AND', new WhereCondition($column, 'NULL', ''));
            }
        } else {
            // $default 非 null（如 '0'、''）：已删行的 column ≠ $default，未删行 = $default
            if (!$query->getState()->where->hasCondition($column, '=', $default)) {
                $query->getState()->where->add('AND', new WhereCondition($column, '=', $default));
            }
        }
    }
}
