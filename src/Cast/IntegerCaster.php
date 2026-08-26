<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

final readonly class IntegerCaster implements Caster
{
    public function fromDb(mixed $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    public function toDb(mixed $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }
}
