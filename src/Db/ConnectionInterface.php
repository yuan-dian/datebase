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

    public function transaction(callable $callback): mixed;

    public function startTrans(): void;

    public function commit(): void;

    public function rollback(): void;

    public function getTableFields(string $tableName): array;

    public function getLastSql(): string;

    public function getLastInsID(BaseQuery $query, ?string $sequence = null);
}
