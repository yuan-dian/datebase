<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

class BelongsToRelation extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        string $foreignKey,
        string $localKey,
    ) {
        parent::__construct($parent, $relatedClass, $foreignKey, $localKey);
    }

    public function getResults(): ?Model
    {
        $foreignKeyProp = StrUtil::camel($this->foreignKey);
        $value = $this->parent->$foreignKeyProp ?? null;

        if ($value === null) {
            return null;
        }

        $localKeyProp = StrUtil::camel($this->localKey);

        return $this->newQuery()
            ->where($localKeyProp, '=', $value)
            ->find();
    }

    public function matchMany(array $localValues): array
    {
        if (empty($localValues)) {
            return [];
        }

        $localKeyProp = StrUtil::camel($this->localKey);
        $grouped = [];

        $models = $this->newQuery()
            ->whereIn($localKeyProp, $localValues)
            ->select();

        foreach ($models as $model) {
            $grouped[$model->$localKeyProp] = $model;
        }

        return $grouped;
    }

    protected function collectLocalValues(array $models): array
    {
        $foreignKeyProp = StrUtil::camel($this->foreignKey);
        $values = [];
        foreach ($models as $model) {
            $value = $model->{$foreignKeyProp} ?? null;
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

    public function eagerLoad(array $models, string $name): array
    {
        $foreignKeyProp = StrUtil::camel($this->foreignKey);
        $values = $this->collectLocalValues($models);

        if (empty($values)) {
            foreach ($models as $model) {
                $model->setRelation($name, null);
            }
            return [];
        }

        $map = $this->matchMany($values);
        $instances = [];

        foreach ($models as $model) {
            $keyVal = $model->{$foreignKeyProp} ?? null;
            $result = $this->extractResult($map[$keyVal] ?? null, $keyVal);
            $model->setRelation($name, $result);

            if ($result !== null) {
                $instances[] = $result;
            }
        }

        return $instances;
    }
}
