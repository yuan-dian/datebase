<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Expression;

class Express
{
    public function __construct(
        protected string $operator,
        protected float|int $step
    ) {}

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getStep(): float|int
    {
        return $this->step;
    }

    public function getValue(): string
    {
        return $this->operator . ' ' . $this->step;
    }
}
