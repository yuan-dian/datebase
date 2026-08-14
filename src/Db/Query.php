<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use PDO;
use yuandian\Database\Model\Model;

/**
 * SQL 查询类
 *
 * @template TModel of Model
 * @extends BaseQuery<TModel>
 */
class Query extends BaseQuery
{
    protected Connection $connection;
    protected Builder $builder;
    /** @var bool 全局作用域是否已应用 */
    protected bool $scopesApplied = false;

    /**
     * @param Connection $connection
     * @param class-string<TModel> $modelClass
     * */
    public function __construct(Connection $connection, string $modelClass)
    {
        parent::__construct($modelClass);

        $this->connection = $connection;
        $this->builder = $connection->getBuilder();
    }

    protected function newSubQuery(): static
    {
        return new static($this->connection, $this->modelClass);
    }

    /**
     * @return TModel|null
     * @date 2026/5/7 上午11:07
     * @author 原点 467490186@qq.com
     */
    public function find(): Model|string|null
    {
        $this->options['limit'] = 1;
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->select($this->options);

        if (!empty($this->options['fetch_sql'])) {
            return $sql;
        }

        $rows = $this->connection->query($sql, array_merge($bind, $this->bind));

        if (empty($rows)) {
            return null;
        }
        $model = $this->toModel($rows[0]);

        if (!empty($this->options['with'])) {
            $model->load(...$this->options['with']);
        }

        return $model;
    }

    /**
     * @return TModel[]
     * @date 2026/5/7 上午11:14
     * @author 原点 467490186@qq.com
     */
    public function select(): array|string
    {
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->select($this->options);

        if (!empty($this->options['fetch_sql'])) {
            return $sql;
        }

        $rows = $this->connection->query($sql, array_merge($bind, $this->bind));

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->toModel($row);
        }

        if (!empty($this->options['with']) && !empty($models)) {
            $this->eagerLoadRelations($models, $this->options['with']);
        }

        return $models;
    }

    public function value(string $field, mixed $default = null): mixed
    {
        $this->applyGlobalScopes();

        $opts = $this->options;
        $opts['field'] = [$field];
        $opts['limit'] = 1;

        [$sql, $bind] = $this->builder->select($opts);
        $rows = $this->connection->query($sql, array_merge($bind, $this->bind));

        if (empty($rows)) {
            return $default;
        }

        $row = $rows[0];
        return reset($row);
    }

    public function column(string $field, string $key = ''): array
    {
        $this->applyGlobalScopes();

        $opts = $this->options;
        $opts['field'] = [$field];

        [$sql, $bind] = $this->builder->select($opts);
        $rows = $this->connection->query($sql, array_merge($bind, $this->bind));

        if (empty($rows)) {
            return [];
        }

        if ($key) {
            return array_column($rows, $field, $key);
        }

        return array_column($rows, $field);
    }

    public function cursor(): \Generator
    {
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->select($this->options);
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute(array_merge($bind, $this->bind));

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->toModel($row);
        }
    }

    public function insert(array $data): int
    {
        [$sql, $bind] = $this->builder->insert($this->options['table'], $data);
        $this->connection->execute($sql, $bind);

        return (int)$this->connection->getLastInsertId();
    }

    public function insertAll(array $dataList): int
    {
        [$sql, $bind] = $this->builder->insertAll($this->options['table'], $dataList);
        $this->connection->execute($sql, $bind);

        return count($dataList);
    }

    public function update(array $data): int
    {
        $this->applyGlobalScopes();

        [$sql, $bind] = $this->builder->update(
            $this->options['table'],
            $data,
            $this->options['where']
        );

        return $this->connection->execute($sql, array_merge($bind, $this->bind));
    }

    public function delete(): int
    {
        $this->applyGlobalScopes();

        $softDelete = $this->modelClass::getSoftDelete();
        if ($softDelete && $softDelete->enabled) {
            return $this->update([$softDelete->column => date('Y-m-d H:i:s')]);
        }
        [$sql, $bind] = $this->builder->delete(
            $this->options['table'],
            $this->options['where']
        );

        return $this->connection->execute($sql, array_merge($bind, $this->bind));
    }

    public function forceDelete(): int
    {
        [$sql, $bind] = $this->builder->delete(
            $this->options['table'],
            $this->options['where']
        );

        return $this->connection->execute($sql, array_merge($bind, $this->bind));
    }

    protected function applyGlobalScopes(): void
    {
        if ($this->withoutScopes || $this->scopesApplied) {
            return;
        }

        $this->scopesApplied = true;
        $softDelete = $this->modelClass::getSoftDelete();
        if ($softDelete && $softDelete->enabled) {
            $this->whereNull($softDelete->column);
        }
    }

    public function eagerLoadRelations(array $models, array $relations): void
    {
        foreach ($models as $model) {
            $model->load(...$relations);
        }
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
