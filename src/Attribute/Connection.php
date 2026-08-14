<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Connection
{
    public function __construct(public readonly string $name)
    {
    }
}
