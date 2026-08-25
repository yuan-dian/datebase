<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Tools\bean\BeanUtil;

/**
 * 模型属性访问与序列化：toArray/toJson、JSON 编解码、脏数据构建。
 *
 * @date 2026/8/24
 * @author 原点 <467490186@qq.com>
 */
trait HasAttributes
{
    public function toArray(): array
    {
        $data = [];
        $columnMap = static::getColumnMap();

        foreach ($columnMap as $prop => $column) {
            if (($this->$prop ?? null) !== null) {
                $data[$prop] = $this->$prop;
            }
        }

        foreach (static::getMeta()->relations as $prop => $relation) {
            if (($this->$prop ?? null) !== null) {
                $data[$prop] = $this->$prop;
            }
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public static function castToJson(mixed $value): ?string
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
            return json_encode(BeanUtil::objectToArray($value));
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param mixed $value 数据库原始值（string / array / null）
     * @param class-string<Object>|null $castTo 目标类名，null → 原生数组
     * @return array|object|null 反序列化结果
     */
    public static function castFromJson(mixed $value, ?string $castTo): array|object|null
    {
        if (empty($value)) {
            return $castTo !== null ? null : [];
        }
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (is_object($value)) {
            $value = $value instanceof \stdClass ? (array)$value : BeanUtil::objectToArray($value);
        }
        if ($castTo === null) {
            return $value;
        }

        if (empty($value)) {
            return [];
        }

        $isList = is_array($value) && array_is_list($value) && !empty($value) && is_array($value[0]);

        return $isList ? BeanUtil::arrayToObjectList($value, $castTo) : BeanUtil::arrayToObject($value, $castTo);
    }

    protected function buildDataForSave(bool $dirtyOnly = false): array
    {
        $data = [];
        $columnMap = static::getColumnMap();
        $jsonColumns = static::getJsonColumns();

        foreach ($columnMap as $prop => $column) {
            if (!property_exists($this, $prop)) {
                continue;
            }

            $value = $this->$prop;

            if (array_key_exists($prop, $jsonColumns)) {
                $value = static::castToJson($value);
            }

            if ($dirtyOnly) {
                if (($this->original[$column] ?? null) !== $value) {
                    $data[$column] = $value;
                }
            } else {
                if ($value !== null) {
                    $data[$column] = $value;
                }
            }
        }
        return $data;
    }

    protected function getInsertData(): array
    {
        return $this->buildDataForSave(dirtyOnly: false);
    }

    protected function getUpdateData(): array
    {
        return $this->buildDataForSave(dirtyOnly: true);
    }
}
