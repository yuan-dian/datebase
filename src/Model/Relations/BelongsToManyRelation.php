<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

class BelongsToManyRelation extends Relation
{
    protected string $through;
    protected string $relatedKey;
    protected string $relatedPivotKey;

    public function __construct(
        Model $parent,
        string $relatedClass,
        string $throughClass,
        string $foreignKey,
        string $relatedKey,
        string $localKey,
        string $relatedPivotKey,
    ) {
        parent::__construct($parent, $relatedClass, $foreignKey, $localKey);
        $this->through = $throughClass;
        $this->relatedKey = $relatedKey;
        $this->relatedPivotKey = $relatedPivotKey;
    }

    public function getResults(): array
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $value = $this->parent->$localKeyProp ?? null;

        if ($value === null) {
            return [];
        }

        $foreignKeyProp = StrUtil::camel($this->foreignKey);
        $relatedKeyProp = StrUtil::camel($this->relatedKey);
        $relatedPivotKeyProp = StrUtil::camel($this->relatedPivotKey);

        $pivotRows = $this->newQueryFor($this->through)
            ->where($foreignKeyProp, '=', $value)
            ->select();

        if (empty($pivotRows)) {
            return [];
        }

        $relatedIds = array_unique(array_column($pivotRows, $relatedKeyProp));

        return $this->newQuery()
            ->whereIn($relatedPivotKeyProp, $relatedIds)
            ->select();
    }

    public function matchMany(array $localValues): array
    {
        if (empty($localValues)) {
            return [];
        }

        $foreignKeyProp = StrUtil::camel($this->foreignKey);
        $relatedKeyProp = StrUtil::camel($this->relatedKey);
        $relatedPivotKeyProp = StrUtil::camel($this->relatedPivotKey);

        $pivotRows = $this->newQueryFor($this->through)
            ->whereIn($foreignKeyProp, $localValues)
            ->select();

        if (empty($pivotRows)) {
            return [];
        }

        $relatedIds = [];
        $pivotMap = [];
        foreach ($pivotRows as $row) {
            $fk = $row[$foreignKeyProp];
            $rk = $row[$relatedKeyProp];
            $relatedIds[] = $rk;
            $pivotMap[$fk][] = $rk;
        }

        $relatedIds = array_unique($relatedIds);

        $relatedModels = $this->newQuery()
            ->whereIn($relatedPivotKeyProp, $relatedIds)
            ->select();

        $byPk = [];
        foreach ($relatedModels as $model) {
            $byPk[$model->$relatedPivotKeyProp] = $model;
        }

        $grouped = [];
        foreach ($pivotMap as $fk => $relatedIdsForFk) {
            $items = [];
            foreach ($relatedIdsForFk as $rid) {
                if (isset($byPk[$rid])) {
                    $items[] = $byPk[$rid];
                }
            }
            $grouped[$fk] = $items;
        }

        return $grouped;
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
        return $group ?? [];
    }
}
