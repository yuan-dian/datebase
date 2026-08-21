<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

class HasManyThroughRelation extends Relation
{
    protected string $through;
    protected string $throughKey;
    protected string $throughPk;

    public function __construct(
        Model $parent,
        string $related,
        string $through,
        ?string $foreignKey = null,
        ?string $throughKey = null,
        ?string $localKey = null,
        ?string $throughPk = null,
    ) {
        parent::__construct(
            $parent,
            $related,
            $foreignKey ?: $parent::getTableName() . '_id',
            $localKey ?: $parent::getPkColumn()
        );

        $this->through = $through;
        $this->throughKey = $throughKey ?: $through::getTableName() . '_id';
        $this->throughPk = $throughPk ?: 'id';
    }

    public function getResults(): array
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $localValue = $this->parent->$localKeyProp ?? null;

        if ($localValue === null) {
            return [];
        }

        // Step 1: 查中间表
        $throughQuery = $this->newQueryFor($this->through);
        $throughModels = $throughQuery
            ->where($this->foreignKey, '=', $localValue)
            ->select();

        if (empty($throughModels)) {
            return [];
        }

        // Step 2: 收集中间表中指向目标表的列值（throughKey 列的值 = 目标表主键值）
        $throughKeyProp = StrUtil::camel($this->throughKey);
        $throughKeyVals = [];
        foreach ($throughModels as $tm) {
            $throughKeyVals[] = $tm->$throughKeyProp;
        }
        $throughKeyVals = array_values(array_unique($throughKeyVals));

        if (empty($throughKeyVals)) {
            return [];
        }

        // Step 3: 在目标表按主键 IN 查最终关联
        return $this->newQuery()
            ->whereIn($this->related::getPkColumn(), $throughKeyVals)
            ->select();
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

    public function matchMany(array $localValues): array
    {
        if ($localValues === []) {
            return [];
        }

        $foreignKeyProp = StrUtil::camel($this->foreignKey);
        $throughKeyProp = StrUtil::camel($this->throughKey);

        // Step 1: 查中间表
        $throughModels = $this->newQueryFor($this->through)
            ->whereIn($this->foreignKey, array_values($localValues))
            ->select();

        // Step 2: 收集中间表中指向目标表的列值（throughKey 列的值 = 目标表主键值）
        $throughKeyVals = [];
        foreach ($throughModels as $throughModel) {
            $throughKeyVals[] = $throughModel->$throughKeyProp;
        }
        $throughKeyVals = array_values(array_unique($throughKeyVals));

        if (empty($throughKeyVals)) {
            return [];
        }

        // Step 3: 一次查询最终关联（在目标表按主键 IN）
        $finalModels = $this->newQuery()
            ->whereIn($this->related::getPkColumn(), $throughKeyVals)
            ->select();

        // Step 4: 按目标表主键值分组
        $byPk = [];
        $relatedPkProp = StrUtil::camel($this->related::getPkColumn());
        foreach ($finalModels as $final) {
            $byPk[$final->$relatedPkProp][] = $final;
        }

        // Step 5: 中间表按 foreignKey 分组，把 throughKey 值 → 最终关联映射到每个父模型
        $grouped = [];
        foreach ($throughModels as $throughModel) {
            $fkValue = $throughModel->$foreignKeyProp;
            $tkValue = $throughModel->$throughKeyProp;
            foreach ($byPk[$tkValue] ?? [] as $final) {
                $grouped[$fkValue][] = $final;
            }
        }

        return $grouped;
    }
}
