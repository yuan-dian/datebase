<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;
use yuandian\Database\Tests\Fixture\Scope\ActiveScope;

#[Table('test_scope_user')]
class ScopeTestUser extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';

    public string $status = 'active';

    public int $age = 0;

    protected static function registerScopes(): void
    {
        static::addGlobalScope(ActiveScope::class);
    }
}
