<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use PDO;
use yuandian\Database\Exceptions\DbException;

/**
 * 查询器（Db 层）：仅依赖数据表，返回原生数组，不感知模型。
 *
 * 模型相关能力（水合、软删除、全局作用域、关联预加载）由 Model\ModelQuery 继承后提供。
 *
 * @date 2026/5/7 上午11:07
 * @author 原点 467490186@qq.com
 */
class Query extends BaseQuery
{
    protected Connection $connection;
    protected Builder $builder;

    /**
     * @param Connection $connection
     * @param string|null $table 数据表名
     */
    public function __construct(Connection $connection, ?string $table = null)
    {
        parent::__construct($table);

        $this->connection = $connection;
        $this->builder = $connection->getBuilder();
    }

    protected function newSubQuery(): static
    {
        return new static($this->connection, $this->options['table'] ?: null);
    }

    /**
     * 查询单条记录
     *
     * @return array<string, mixed>|string|null 行数据；fetchSql 模式返回 SQL
     */
    public function find()
    {
        $this->ensureTable();
        $this->options['limit'] = 1;
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->select($this->options);

        if (!empty($this->options['fetch_sql'])) {
            return $sql;
        }

        $rows = $this->connection->query($sql, array_merge($bind, $this->bind));

        return $rows[0] ?? null;
    }

    /**
     * 查询多条记录
     *
     * @return list<array<string, mixed>>|string 行数据数组；fetchSql 模式返回 SQL
     */
    public function select()
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->select($this->options);

        if (!empty($this->options['fetch_sql'])) {
            return $sql;
        }

        return $this->connection->query($sql, array_merge($bind, $this->bind));
    }

    /**
     * 查询单个字段值
     *
     * @param string $field 字段名
     * @param mixed $default 无结果时的默认值
     * @return mixed
     */
    public function value(string $field, mixed $default = null): mixed
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        $opts = $this->options;
        $opts['field'] = [$field];
        $opts['limit'] = 1;

        [$sql, $bind] = $this->builder->select($opts);

        if (!empty($this->options['fetch_sql'])) {
            return $sql;
        }

        $rows = $this->connection->query($sql, array_merge($bind, $this->bind));

        if (empty($rows)) {
            return $default;
        }

        return reset($rows[0]);
    }

    /**
     * 查询单列数据
     *
     * @param string $field 字段名
     * @param string $key 以该字段的值作为数组键
     * @return array|string 列数据数组；fetchSql 模式返回 SQL
     */
    public function column(string $field, string $key = ''): array|string
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        $opts = $this->options;
        $opts['field'] = [$field];

        [$sql, $bind] = $this->builder->select($opts);

        if (!empty($this->options['fetch_sql'])) {
            return $sql;
        }

        $rows = $this->connection->query($sql, array_merge($bind, $this->bind));

        if (empty($rows)) {
            return [];
        }

        if ($key) {
            return array_column($rows, $field, $key);
        }

        return array_column($rows, $field);
    }

    /**
     * 游标式逐行查询
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function cursor(): \Generator
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        if (!empty($this->options['fetch_sql'])) {
            throw new DbException('fetchSql 模式不支持游标');
        }

        [$sql, $bind] = $this->builder->select($this->options);
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute(array_merge($bind, $this->bind));

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    public function insert(array $data): int
    {
        $this->ensureTable();

        [$sql, $bind] = $this->builder->insert($this->options['table'], $data);
        $this->connection->execute($sql, $bind);

        return (int)$this->connection->getLastInsID($this);
    }

    public function insertAll(array $dataList): int
    {
        // 空数组短路：避免生成空 SQL 导致 PDO prepare('') 异常
        if (empty($dataList)) {
            return 0;
        }

        $this->ensureTable();

        [$sql, $bind] = $this->builder->insertAll($this->options['table'], $dataList);
        $this->connection->execute($sql, $bind);

        return count($dataList);
    }

    public function update(array $data): int
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->update(
            $this->options['table'],
            $data,
            $this->options['where']
        );

        return $this->connection->execute($sql, array_merge($bind, $this->bind));
    }

    /**
     * 物理删除
     */
    public function delete(): int
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->delete(
            $this->options['table'],
            $this->options['where']
        );

        return $this->connection->execute($sql, array_merge($bind, $this->bind));
    }

    /**
     * 物理删除（跳过软删除与全局作用域）
     */
    public function forceDelete(): int
    {
        $this->ensureTable();

        [$sql, $bind] = $this->builder->delete(
            $this->options['table'],
            $this->options['where']
        );

        return $this->connection->execute($sql, array_merge($bind, $this->bind));
    }

    /**
     * 全局作用域：Db 层不感知模型，交由子类（ModelQuery）覆写
     */
    protected function applyGlobalScopes(): void
    {
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getBuilder(): Builder
    {
        return $this->builder;
    }
}