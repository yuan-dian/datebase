<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Expression;

class BindContext
{
    private array $params = [];

    public function add(mixed $value): void
    {
        $this->params[] = $value;
    }

    public function merge(array $values): void
    {
        array_push($this->params, ...$values);
    }

    public function all(): array
    {
        return $this->params;
    }
}
