<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    /**
     * @param string $model
     * @param string|null $foreignKey
     * @param string|null $localKey
     */
    public function __construct(
        public readonly string $model,
        public readonly ?string $foreignKey = null,
        public readonly ?string $localKey = null,
    ) {
    }
}
