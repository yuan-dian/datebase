<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

class HasOneRelation extends Relation
{
    public function __construct(
        Model $parent,
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ) {
        parent::__construct(
            $parent,
            $related,
            $foreignKey ?? $parent::getTableName() . '_id',
            $localKey ?: $parent::getPkColumn()
        );
    }

    public function getResults(): ?Model
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $localValue = $this->parent->$localKeyProp ?? null;

        if ($localValue === null) {
            return null;
        }

        return $this->newQuery()
            ->where($this->foreignKey, '=', $localValue)
            ->find();
    }

    protected function collectLocalValues(array $models): array
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $values = [];
        foreach ($models as $model) {
            $value = $model->{$localKeyProp} ?? null;
            if ($value !== null) {
                $values[] = $value;
            }
        }
        return array_values(array_unique($values));
    }

    protected function extractResult(mixed $group, mixed $key): mixed
    {
        return $group ?? null;
    }

    public function matchMany(array $localValues): array
    {
        if ($localValues === []) {
            return [];
        }

        $models = $this->newQuery()
            ->whereIn($this->foreignKey, array_values($localValues))
            ->select();

        $foreignKeyProp = StrUtil::camel($this->foreignKey);

        $grouped = [];
        foreach ($models as $model) {
            $value = $model->{$foreignKeyProp} ?? null;
            if ($value !== null) {
                $grouped[$value] = $model;
            }
        }

        return $grouped;
    }
}
