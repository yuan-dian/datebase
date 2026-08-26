<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

final readonly class BooleanCaster implements Caster
{
    public function fromDb(mixed $value): ?bool
    {
        return $value !== null ? (bool) $value : null;
    }

    public function toDb(mixed $value): ?bool
    {
        return $value !== null ? (bool) $value : null;
    }
}
