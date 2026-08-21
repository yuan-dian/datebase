<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

class HasOneThroughRelation extends Relation
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

    public function getResults(): ?Model
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $localValue = $this->parent->$localKeyProp ?? null;

        if ($localValue === null) {
            return null;
        }

        // Step 1: 查中间表（用 throughKey 过滤：中间表的外键指向父表的列）
        $throughQuery = $this->newQueryFor($this->through);
        $throughModel = $throughQuery
            ->where($this->throughKey, '=', $localValue)
            ->find();

        if ($throughModel === null) {
            return null;
        }

        // Step 2: 用中间表主键值（中间表主键 = 目标表外键列的值）查最终关联
        $throughPkProp = StrUtil::camel($this->throughPk);
        $throughKeyVal = $throughModel->$throughPkProp ?? null;

        if ($throughKeyVal === null) {
            return null;
        }

        return $this->newQuery()
            ->where($this->related::getPkColumn(), '=', $throughKeyVal)
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

        $foreignKeyProp = StrUtil::camel($this->foreignKey);
        $throughKeyProp = StrUtil::camel($this->throughKey);
        $throughPkProp = StrUtil::camel($this->throughPk);

        // Step 1: 查中间表（用 throughKey 过滤：中间表的外键指向父表的列）
        $throughModels = $this->newQueryFor($this->through)
            ->whereIn($this->throughKey, array_values($localValues))
            ->select();

        // Step 2: 收集中间表主键值（中间表主键 = 目标表外键列的值）
        $throughKeyVals = [];
        foreach ($throughModels as $throughModel) {
            $throughKeyVals[] = $throughModel->$throughPkProp;
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

        // Step 5: 中间表按 throughKey 分组（中间表的外键指向父表的列），把最终关联映射到每个父模型
        $grouped = [];
        foreach ($throughModels as $throughModel) {
            $userId = $throughModel->$throughKeyProp;
            $postId = $throughModel->$throughPkProp;
            foreach ($byPk[$postId] ?? [] as $final) {
                $grouped[$userId][] = $final;
            }
        }

        // Step 6: HasOneThrough 每组只取第一条
        foreach ($grouped as $fkValue => $items) {
            $grouped[$fkValue] = $items[0];
        }

        return $grouped;
    }
}
