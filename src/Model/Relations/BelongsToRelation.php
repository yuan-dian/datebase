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
        return $this->collectValuesByColumn($models, $this->foreignKey);
    }

    protected function getLookupKeyProperty(): string
    {
        return StrUtil::camel($this->foreignKey);
    }
}
