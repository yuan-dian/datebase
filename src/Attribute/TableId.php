<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;
use yuandian\Database\Enums\IdType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class TableId
{
    public function __construct(public readonly IdType $type = IdType::AUTO)
    {
    }
}
