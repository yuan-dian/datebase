<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Enums\RelationType;
use yuandian\Database\Model\Model;

class RelationFactory
{
    public static function create(Model $parent, RelationType $type, object $attr): ?Relation
    {
        return match ($type) {
            RelationType::HasOne => new HasOneRelation(
                $parent, $attr->model, $attr->foreignKey, $attr->localKey
            ),
            RelationType::HasMany => new HasManyRelation(
                $parent, $attr->model, $attr->foreignKey, $attr->localKey
            ),
            RelationType::HasOneThrough => new HasOneThroughRelation(
                $parent, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ),
            RelationType::HasManyThrough => new HasManyThroughRelation(
                $parent, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ),
            RelationType::BelongsTo => new BelongsToRelation(
                $parent, $attr->model, $attr->foreignKey, $attr->ownerKey
            ),
            RelationType::BelongsToMany => new BelongsToManyRelation(
                $parent, $attr->model, $attr->through,
                $attr->foreignKey, $attr->relatedKey, $attr->localKey, $attr->relatedPivotKey
            ),
            default => null,
        };
    }
}
