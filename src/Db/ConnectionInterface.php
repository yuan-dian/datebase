<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\DbManager;

interface ConnectionInterface
{
    public function getQueryClass(): string;

    public function connect(array $config = [], int $linkNum = 0): mixed;

    public function setDb(DbManager $db): void;

    public function getConfig(string $key = ''): mixed;

    public function close(): void;

    public function transaction(callable $callback): mixed;

    public function startTrans(): void;

    public function commit(): void;

    public function rollback(): void;

    public function getTableFields(string $tableName): array;

    public function getLastSql(): string;

    public function getLastInsertId(BaseQuery $query, ?string $sequence = null): string|false;
}
