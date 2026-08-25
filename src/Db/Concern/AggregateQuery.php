<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Concern;

use yuandian\Database\Db\Expression\Raw;

trait AggregateQuery
{
    public function count(string $field = '*'): int
    {
        return (int)$this->aggregate('COUNT', $field);
    }

    public function sum(string $field): float
    {
        return (float)$this->aggregate('SUM', $field);
    }

    public function min(string $field): string|int|float|null
    {
        return $this->aggregate('MIN', $field);
    }

    public function max(string $field): string|int|float|null
    {
        return $this->aggregate('MAX', $field);
    }

    public function avg(string $field): float
    {
        return (float)$this->aggregate('AVG', $field);
    }

    protected function aggregate(string $fn, string $field): string|int|float|null
    {
        $this->applyGlobalScopes();

        // 防注入：聚合字段仅允许 * 或 表.列 形式的标识符
        if ($field !== '*' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $field)) {
            throw new \InvalidArgumentException("Invalid aggregate field: {$field}");
        }

        $state = $this->state->copy();
        $state->order = [];
        $state->limit = null;
        $state->offset = null;

        // 带 GROUP BY 时：子查询包裹，避免 COUNT 只取第一组
        if (!empty($state->group)) {
            $state->field = ['*'];
            $subCompiled = $this->getBuilder()->compileSelect($state);

            $sql = "SELECT {$fn}({$field}) AS __agg FROM ({$subCompiled->statement}) AS __agg_tmp";
            $bind = array_merge($subCompiled->bind, $this->bind);
        } else {
            $state->field = [new Raw("{$fn}($field) AS __agg")];
            $compiled = $this->getBuilder()->compileSelect($state);
            $sql = $compiled->statement;
            $bind = array_merge($compiled->bind, $this->bind);
        }

        /** @var \yuandian\Database\Db\PDOConnection $connection 聚合仅在 PDO 连接上执行（Mongo 走覆写路径） */
        $connection = $this->getConnection();
        $rows = $connection->query($sql, $bind);

        return $rows[0]['__agg'] ?? null;
    }

    abstract protected function getBuilder(): \yuandian\Database\Db\BuilderInterface;

    abstract protected function getConnection(): \yuandian\Database\Db\Connection;

    abstract protected function applyGlobalScopes(): void;
}
