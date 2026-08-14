<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Db\Query;
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

    public function getResults(): ?Model
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $localValue = $this->parent->$localKeyProp ?? null;

        if ($localValue === null) {
            return null;
        }

        // Step 1: 查中间表
        $throughQuery = new Query($this->through);
        $throughModel = $throughQuery
            ->where($this->foreignKey, '=', $localValue)
            ->find();

        if ($throughModel === null) {
            return null;
        }

        // Step 2: 用中间表主键查最终关联
        $throughPkProp = StrUtil::camel($this->throughPk);
        $throughPkVal = $throughModel->$throughPkProp;

        return $this->newQuery()
            ->where($this->throughKey, '=', $throughPkVal)
            ->find();
    }
}
