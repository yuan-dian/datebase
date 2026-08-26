<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

interface Caster
{
    public function fromDb(mixed $value): mixed;

    public function toDb(mixed $value): mixed;
}
