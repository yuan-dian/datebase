<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Expression;

class WhereClause
{
    public function __construct(
        public readonly string $field,
        public readonly string $key,
        public readonly string $operator,
        public readonly mixed  $value,
    ) {}
}
