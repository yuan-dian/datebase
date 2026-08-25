<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Expression;

class Raw
{
    public function __construct(
        protected string $value,
        protected array $bind = []
    ) {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getBind(): array
    {
        return $this->bind;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
