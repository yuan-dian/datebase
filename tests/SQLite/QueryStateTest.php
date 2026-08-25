<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Expression\QueryState;
use yuandian\Database\Db\Expression\WhereCondition;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\User;

class QueryStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL DEFAULT "",
            age INTEGER NOT NULL DEFAULT 0
        )');
    }

    public function testBasicSelect(): void
    {
        $this->seedRows('test_user', ['name', 'email', 'age'], [
            ['Alice', 'alice@example.com', 25],
            ['Bob', 'bob@example.com', 30],
        ]);

        $results = User::select();

        $this->assertCount(2, $results);
        $this->assertSame('Alice', $results[0]->name);
        $this->assertSame('Bob', $results[1]->name);
    }

    public function testWhereWithBind(): void
    {
        $this->seedRows('test_user', ['name', 'email', 'age'], [
            ['Alice', 'alice@example.com', 25],
            ['Bob', 'bob@example.com', 30],
            ['Charlie', 'charlie@example.com', 25],
        ]);

        $results = User::where('name', '=', 'Alice')->select();

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results[0]->name);
        $this->assertSame('alice@example.com', $results[0]->email);
    }

    public function testHasConditionIdempotency(): void
    {
        $query = User::where('name', '=', 'Alice');
        $state = $query->getState();

        $this->assertTrue($state->where->hasCondition('name', '='));
        $this->assertFalse($state->where->hasCondition('name', '!='));
        $this->assertFalse($state->where->hasCondition('email', '='));

        $query->where('name', '=', 'Bob');
        $this->assertCount(2, $state->where->and);
        $this->assertTrue($state->where->hasCondition('name', '='));
    }

    public function testCopyIsolation(): void
    {
        $state = new QueryState();
        $state->table = 'test_user';
        $state->where->add('AND', new WhereCondition('id', '=', 1));

        $copy = $state->copy();
        $copy->where->add('AND', new WhereCondition('name', '=', 'Alice'));

        $this->assertCount(1, $state->where->and);
        $this->assertCount(2, $copy->where->and);
        $this->assertSame('test_user', $state->table);
    }
}
