<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Expression;

readonly class WhereClause
{
    public function __construct(
        public string $field,
        public string $key,
        public string $operator,
        public mixed  $value,
    ) {}
}
