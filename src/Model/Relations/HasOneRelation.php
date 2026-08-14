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
}
