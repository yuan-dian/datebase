<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Db\Query;
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

    public function getResults(): array
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $localValue = $this->parent->$localKeyProp ?? null;

        if ($localValue === null) {
            return [];
        }

        // Step 1: 查中间表
        $throughQuery = new Query($this->through);
        $throughModels = $throughQuery
            ->where($this->foreignKey, '=', $localValue)
            ->select();

        if (empty($throughModels)) {
            return [];
        }

        // Step 2: 收集中间表主键
        $throughPkProp = StrUtil::camel($this->throughPk);
        $throughPkVals = [];
        foreach ($throughModels as $tm) {
            $throughPkVals[] = $tm->$throughPkProp;
        }

        // Step 3: 查最终关联
        return $this->newQuery()
            ->whereIn($this->throughKey, $throughPkVals)
            ->select();
    }
}
