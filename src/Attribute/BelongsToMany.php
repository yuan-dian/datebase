<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsToMany
{
    public function __construct(
        public readonly string $model,
        public readonly string $through,
        public readonly string $foreignKey,
        public readonly string $relatedKey,
        public readonly ?string $localKey = null,
        public readonly ?string $relatedPivotKey = null,
    ) {
    }
}
