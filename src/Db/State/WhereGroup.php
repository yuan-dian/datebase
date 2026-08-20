<?php

declare(strict_types=1);

namespace yuandian\Database\Db\State;

/**
 * 条件组：AND 组与 OR 组。组内同逻辑连接，组间用 OR 连接（与原 $options['where'] 语义一致，
 * 但不再依赖数组键遍历顺序）。
 */
final class WhereGroup
{
    /** @var list<WhereCondition> */
    public array $and = [];

    /** @var list<WhereCondition> */
    public array $or = [];

    public function add(string $logic, WhereCondition $condition): void
    {
        if (strtoupper($logic) === 'OR') {
            $this->or[] = $condition;
        } else {
            $this->and[] = $condition;
        }
    }

    /**
     * 是否存在 字段+运算符 的既有条件（软删幂等检查用）
     */
    public function hasCondition(string $field, string $operator): bool
    {
        foreach ([$this->and, $this->or] as $conditions) {
            foreach ($conditions as $c) {
                if ($c->field === $field && strtoupper($c->operator) === strtoupper($operator)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function isEmpty(): bool
    {
        return $this->and === [] && $this->or === [];
    }
}
