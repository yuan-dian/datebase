<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

use BackedEnum;

final readonly class EnumCaster implements Caster
{
    public function __construct(
        private string $class,
    ) {
    }

    public function fromDb(mixed $value): ?BackedEnum
    {
        if ($value === null) {
            return null;
        }

        $enumClass = $this->class;

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new \InvalidArgumentException("Class {$enumClass} is not a backed enum.");
        }

        $reflection = new \ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType();

        if ($backingType !== null && $backingType->getName() === 'string') {
            return $enumClass::from((string) $value);
        }

        return $enumClass::from((int) $value);
    }

    public function toDb(mixed $value): string|int|null
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return null;
    }
}
