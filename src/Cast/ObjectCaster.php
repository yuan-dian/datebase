<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

final class ObjectCaster implements Caster
{
    public function __construct(
        private readonly string $class,
    ) {
    }

    public function fromDb(mixed $value): ?object
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_object($value) && $value instanceof $this->class) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return null;
        }

        return \yuandian\Tools\bean\BeanUtil::arrayToObject($value, $this->class);
    }

    public function toDb(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \JsonSerializable) {
            return json_encode($value->jsonSerialize(), JSON_UNESCAPED_UNICODE);
        }

        if (is_object($value)) {
            return json_encode(\yuandian\Tools\bean\BeanUtil::objectToArray($value), JSON_UNESCAPED_UNICODE);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
