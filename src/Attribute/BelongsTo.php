<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo
{
    public function __construct(
        public readonly string $model,
        public readonly ?string $foreignKey = null,
        public readonly ?string $ownerKey = null,
    ) {
    }
}
