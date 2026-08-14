<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasManyThrough
{
    public function __construct(
        public readonly string $model,
        public readonly string $through,
        public readonly ?string $foreignKey = null,
        public readonly ?string $throughKey = null,
        public readonly ?string $localKey = null,
        public readonly ?string $throughPk = null,
    ) {
    }
}
