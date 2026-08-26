<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Scope;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Scope\Scope;

class ActiveScope implements Scope
{
    public function apply(BaseQuery $query, string $modelClass): void
    {
        $query->where('status', '=', 'active');
    }
}
