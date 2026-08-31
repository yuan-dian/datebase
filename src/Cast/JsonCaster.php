<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

final class JsonCaster implements Caster
{
    public function __construct(
        private readonly ?string $castTo = null,
    ) {
    }

    public function fromDb(mixed $value): array|object|null
    {
        if ($value === null || $value === '') {
            return $this->castTo !== null ? null : [];
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (is_object($value)) {
            $value = $value instanceof \stdClass ? (array) $value : \yuandian\Tools\bean\BeanUtil::objectToArray($value);
        }

        if (!is_array($value)) {
            return $this->castTo !== null ? null : [];
        }

        if ($this->castTo === null) {
            return $value;
        }

        if (empty($value)) {
            return [];
        }

        $isList = is_array($value) && array_is_list($value) && !empty($value) && is_array($value[0]);

        return $isList
            ? \yuandian\Tools\bean\BeanUtil::arrayToObjectList($value, $this->castTo)
            : \yuandian\Tools\bean\BeanUtil::arrayToObject($value, $this->castTo);
    }

    public function toDb(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }

        if ($value instanceof \JsonSerializable) {
            return json_encode($value->jsonSerialize(), JSON_UNESCAPED_UNICODE);
        }

        if (is_object($value)) {
            return json_encode(\yuandian\Tools\bean\BeanUtil::objectToArray($value), JSON_UNESCAPED_UNICODE);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
