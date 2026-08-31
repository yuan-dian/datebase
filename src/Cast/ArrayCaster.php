<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

final class ArrayCaster implements Caster
{
    public function __construct(
        private readonly ?Caster $elementCaster = null,
    ) {
    }

    public function fromDb(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return [$value];
        }

        if ($this->elementCaster !== null) {
            return array_map(
                fn(mixed $item) => $this->elementCaster->fromDb($item),
                $value,
            );
        }

        return $value;
    }

    public function toDb(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        if ($this->elementCaster !== null) {
            $value = array_map(
                fn(mixed $item) => $this->elementCaster->toDb($item),
                $value,
            );
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
