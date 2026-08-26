<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

final readonly class FloatCaster implements Caster
{
    public function fromDb(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }

    public function toDb(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}
