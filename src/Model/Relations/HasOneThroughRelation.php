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
        $this->throughPk = $throughPk ?? 'id';
    }

    public function getThrough(): string
    {
        return $this->through;
    }

    public function getThroughKey(): string
    {
        return $this->throughKey;
    }

    public function getThroughPk(): string
    {
        return $this->throughPk;
    }

    public function getResults(): ?Model
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $localValue = $this->parent->$localKeyProp ?? null;

        if ($localValue === null) {
            return null;
        }

        // Step 1: 查中间表
        $throughQuery = $this->newQueryFor($this->through);
        $throughModel = $throughQuery
            ->where($this->foreignKey, '=', $localValue)
            ->find();

        if ($throughModel === null) {
            return null;
        }

        // Step 2: 用中间表中指向目标表的列值（throughKey 列的值 = 目标表主键值）查最终关联
        $throughKeyProp = StrUtil::camel($this->throughKey);
        $throughKeyVal = $throughModel->$throughKeyProp ?? null;

        if ($throughKeyVal === null) {
            return null;
        }

        return $this->newQuery()
            ->where($this->related::getPkColumn(), '=', $throughKeyVal)
            ->find();
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

        // Step 6: HasOneThrough 每组取第一条（与原有 eagerLoadHasOneThrough 的 [0] 语义一致）
        foreach ($grouped as $fkValue => $items) {
            $grouped[$fkValue] = $items[0];
        }

        return $grouped;
    }
}
