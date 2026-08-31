<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

final class StringCaster implements Caster
{
    public function fromDb(mixed $value): ?string
    {
        return $value !== null ? (string) $value : null;
    }

    public function toDb(mixed $value): ?string
    {
        return $value !== null ? (string) $value : null;
    }
}
