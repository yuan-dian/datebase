<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

/**
 * Builder 编译结果。SQL 系 statement 为字符串 + bind 数组；
 * Mongo 系 statement 为 MongoDB\Driver\Query/BulkWrite/Command 对象，bind 为空。
 */
final class Compiled
{
    public function __construct(
        public readonly string|object $statement,
        public readonly array $bind = [],
    ) {}
}
