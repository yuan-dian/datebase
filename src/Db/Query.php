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
 * @extends BaseQuery<array<string, mixed>>
 * @date 2026/5/7 上午11:07
 * @author 原点 467490186@qq.com
 */
class Query extends BaseQuery
{
    protected Connection $connection;
    protected BuilderInterface $builder;

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
        $query = new static($this->connection, $this->state->table ?: null);
        $query->state = $this->state->copy();
        return $query;
    }

    /**
     * 查询单条记录
     *
     * 返回类型取公共上界 array|object|null（PHP 协变）：Db 层实际返回数组行；
     * Model 层子类（ModelQuery）收窄为 ?Model，父类声明 ?array 将锁死该收窄。
     *
     * @return array<string, mixed>|object|null 行数据
     */
    public function find(): array|object|null
    {
        $this->ensureTable();
        $this->state->limit = 1;
        $this->applyGlobalScopes();

        $compiled = $this->builder->compileSelect($this->state);

        $rows = $this->connection->query($compiled->statement, array_merge($compiled->bind, $this->bind));

        return $rows[0] ?? null;
    }

    /**
     * 查询多条记录
     *
     * @return list<array<string, mixed>> 行数据数组
     */
    public function select(): array
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        $compiled = $this->builder->compileSelect($this->state);

        return $this->connection->query($compiled->statement, array_merge($compiled->bind, $this->bind));
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

        $state = $this->state->copy();
        $state->field = [$field];
        $state->limit = 1;

        $compiled = $this->builder->compileSelect($state);

        $rows = $this->connection->query($compiled->statement, array_merge($compiled->bind, $this->bind));

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
     * @return array 列数据数组
     */
    public function column(string $field, string $key = ''): array
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        $state = $this->state->copy();
        $state->field = [$field];

        $compiled = $this->builder->compileSelect($state);

        $rows = $this->connection->query($compiled->statement, array_merge($compiled->bind, $this->bind));

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

        $compiled = $this->builder->compileSelect($this->state);

        // 未连接时 getPdo() 返回 false，直接 prepare() 会 fatal；先惰性建立连接
        $pdo = $this->connection->getPdo() ?: $this->connection->connect();
        $stmt = $pdo->prepare($compiled->statement);
        $stmt->execute(array_merge($compiled->bind, $this->bind));

        try {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
            }
        } finally {
            $stmt->closeCursor();
        }
    }

    public function insert(array $data): int
    {
        $this->ensureTable();

        $compiled = $this->builder->compileInsert($this->state->table, $data, $this->state->comment ?: null);
        $this->connection->execute($compiled->statement, $compiled->bind);

        return (int)$this->connection->getLastInsertId($this);
    }

    public function insertAll(array $dataList): int
    {
        // 空数组短路：避免生成空 SQL 导致 PDO prepare('') 异常
        if (empty($dataList)) {
            return 0;
        }

        $this->ensureTable();

        $compiled = $this->builder->compileInsertAll($this->state->table, $dataList, $this->state->comment ?: null);
        $this->connection->execute($compiled->statement, $compiled->bind);

        return count($dataList);
    }

    public function update(array $data): int
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        $compiled = $this->builder->compileUpdate($this->state->table, $data, $this->state);

        return $this->connection->execute($compiled->statement, array_merge($compiled->bind, $this->bind));
    }

    /**
     * 物理删除
     */
    public function delete(): int
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        $compiled = $this->builder->compileDelete($this->state->table, $this->state);

        return $this->connection->execute($compiled->statement, array_merge($compiled->bind, $this->bind));
    }

    /**
     * 构建当前查询的 SQL 而不执行
     *
     * @param bool $sub 是否包裹括号（用于子查询嵌入场景）
     */
    public function buildSql(bool $sub = false): string
    {
        $this->ensureTable();
        $this->applyGlobalScopes();

        $compiled = $this->builder->compileSelect($this->state);

        return $sub ? '( ' . $compiled->statement . ' )' : $compiled->statement;
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

    public function getBuilder(): BuilderInterface
    {
        return $this->builder;
    }
}