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

        $opts = $this->options;
        $opts['field'] = [new Raw("{$fn}($field) AS __agg")];
        unset($opts['order'], $opts['limit'], $opts['offset']);

        [$sql, $bind] = $this->getBuilder()->select($opts);
        $rows = $this->getConnection()->query($sql, array_merge($bind, $this->bind));

        return $rows[0]['__agg'] ?? null;
    }

    abstract protected function getBuilder(): \yuandian\Database\Db\Builder|\yuandian\Database\Db\Builder\Mongo;

    abstract protected function getConnection(): \yuandian\Database\Db\Connection;

    abstract protected function applyGlobalScopes(): void;
}
