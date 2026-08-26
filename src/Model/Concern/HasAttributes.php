<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Cast\CasterRegistry;

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

    protected function buildDataForSave(bool $dirtyOnly = false): array
    {
        $data = [];
        $columnMap = static::getColumnMap();
        $propertyTypes = static::getPropertyTypes();

        foreach ($columnMap as $prop => $column) {
            if (!property_exists($this, $prop)) {
                continue;
            }

            $value = $this->$prop;

            $typeInfo = $propertyTypes[$prop] ?? null;
            if ($typeInfo !== null) {
                $caster = CasterRegistry::resolve($typeInfo);
                $value = $caster->toDb($value);
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
