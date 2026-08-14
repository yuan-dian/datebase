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
}
