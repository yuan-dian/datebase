<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\DbManager;

interface ConnectionInterface
{
    public function getQueryClass(): string;

    public function connect(array $config = [], int $linkNum = 0);

    public function setDb(DbManager $db): void;

    public function getConfig(string $key = ''): mixed;

    public function close();

    public function find(BaseQuery $query): array;

    public function select(BaseQuery $query): array;

    public function insert(BaseQuery $query, bool $getLastInsID = false);

    public function insertAll(BaseQuery $query, array $dataSet = []): int;

    public function update(BaseQuery $query): int;

    public function delete(BaseQuery $query): int;

    public function value(BaseQuery $query, string $field, $default = null);

    public function column(BaseQuery $query, string|array $column, string $key = ''): array;

    public function transaction(callable $callback): mixed;

    public function startTrans(): void;

    public function commit(): void;

    public function rollback(): void;

    public function getTableFields(string $tableName): array;

    public function getLastSql(): string;

    public function getLastInsID(BaseQuery $query, ?string $sequence = null);
}
