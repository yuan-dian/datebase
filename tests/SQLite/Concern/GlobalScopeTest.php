<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Concern;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Scope\Scope;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\ScopeTestUser;
use yuandian\Database\Tests\Fixture\Scope\ActiveScope;
use yuandian\Database\Tests\Fixture\Scope\AgeScope;

class GlobalScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();
        $this->createTable('CREATE TABLE test_scope_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "active",
            age INTEGER NOT NULL DEFAULT 0
        )');
    }

    protected function tearDown(): void
    {
        ScopeTestUser::removeGlobalScope(AgeScope::class);
        parent::tearDown();
    }

    private function seedUsers(): void
    {
        foreach ([
            ['Alice', 'active', 25],
            ['Bob', 'inactive', 17],
            ['Charlie', 'active', 30],
            ['Dave', 'active', 15],
        ] as [$name, $status, $age]) {
            $user = new ScopeTestUser();
            $user->name = $name;
            $user->status = $status;
            $user->age = $age;
            $user->insert();
        }
    }

    public function testRegisterScopesAutoApplied(): void
    {
        $this->seedUsers();

        $users = ScopeTestUser::select();

        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $this->assertSame('active', $user->status);
        }
    }

    public function testAddGlobalScopeDynamically(): void
    {
        $this->seedUsers();

        ScopeTestUser::addGlobalScope(AgeScope::class);

        $users = ScopeTestUser::select();

        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertGreaterThan(18, $user->age);
        }
    }

    public function testRemoveGlobalScopeRestoresRows(): void
    {
        $this->seedUsers();

        ScopeTestUser::addGlobalScope(AgeScope::class);
        $users = ScopeTestUser::select();
        $this->assertCount(2, $users);

        ScopeTestUser::removeGlobalScope(AgeScope::class);
        $users = ScopeTestUser::select();
        $this->assertCount(3, $users);
    }

    public function testWithoutGlobalScopeSingle(): void
    {
        $this->seedUsers();

        $users = ScopeTestUser::withoutGlobalScope(ActiveScope::class)->select();

        $this->assertCount(4, $users);
    }

    public function testWithoutGlobalScopesAll(): void
    {
        $this->seedUsers();

        $users = ScopeTestUser::withoutGlobalScopes()->select();

        $this->assertCount(4, $users);
    }

    public function testMultipleScopesStacked(): void
    {
        $this->seedUsers();

        ScopeTestUser::addGlobalScope(AgeScope::class);

        $users = ScopeTestUser::withoutGlobalScope(AgeScope::class)->select();
        $this->assertCount(3, $users);

        $users = ScopeTestUser::withoutGlobalScope(ActiveScope::class)->select();
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertGreaterThan(18, $user->age);
        }
    }

    public function testGetGlobalScopesReturnsRegistered(): void
    {
        $scopes = ScopeTestUser::getGlobalScopes();

        $this->assertArrayHasKey(ActiveScope::class, $scopes);
        $this->assertInstanceOf(Scope::class, $scopes[ActiveScope::class]);
    }

    public function testRemoveGlobalScopeNotRegistered(): void
    {
        ScopeTestUser::removeGlobalScope('NonExistentScope');

        $scopes = ScopeTestUser::getGlobalScopes();
        $this->assertArrayNotHasKey('NonExistentScope', $scopes);
    }
}
