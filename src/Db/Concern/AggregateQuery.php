<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Concern;

use yuandian\Database\Db\Raw;

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

    public function min(string $field): mixed
    {
        return $this->aggregate('MIN', $field);
    }

    public function max(string $field): mixed
    {
        return $this->aggregate('MAX', $field);
    }

    public function avg(string $field): float
    {
        return (float)$this->aggregate('AVG', $field);
    }

    protected function aggregate(string $fn, string $field): mixed
    {
        $this->applyGlobalScopes();

        // 防注入：聚合字段仅允许 * 或 表.列 形式的标识符
        if ($field !== '*' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $field)) {
            throw new \InvalidArgumentException("Invalid aggregate field: {$field}");
        }

        $opts = $this->options;
        unset($opts['order'], $opts['limit'], $opts['offset']);

        // 带 GROUP BY 时：子查询包裹，避免 COUNT GROUP BY 只取第一组（ThinkPHP #2670 同源问题）
        if (!empty($opts['group'])) {
            $opts['field'] = ['*'];
            [$subSql, $subBind] = $this->getBuilder()->select($opts);

            $sql = "SELECT {$fn}({$field}) AS __agg FROM ({$subSql}) AS __agg_tmp";
            $bind = array_merge($subBind, $this->bind);
        } else {
            $opts['field'] = [new Raw("{$fn}($field) AS __agg")];
            [$sql, $bind] = $this->getBuilder()->select($opts);
            $bind = array_merge($bind, $this->bind);
        }

        $rows = $this->getConnection()->query($sql, $bind);

        return $rows[0]['__agg'] ?? null;
    }

    abstract protected function getBuilder(): \yuandian\Database\Db\Builder|\yuandian\Database\Db\Builder\Mongo;

    abstract protected function getConnection(): \yuandian\Database\Db\Connection;

    abstract protected function applyGlobalScopes(): void;
}
