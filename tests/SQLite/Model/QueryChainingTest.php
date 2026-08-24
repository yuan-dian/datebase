<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Model;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\User;

class QueryChainingTest extends TestCase
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

    private function seedUsers(): void
    {
        $data = [
            ['Alice', 'alice@test.com', 25],
            ['Bob', 'bob@test.com', 30],
            ['Charlie', 'charlie@test.com', 35],
            ['Dave', 'dave@test.com', 20],
            ['Eve', 'eve@test.com', 28],
        ];
        foreach ($data as [$name, $email, $age]) {
            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->age = $age;
            $user->insert();
        }
    }

    public function testWhereChain(): void
    {
        $this->seedUsers();

        $users = User::where('age', '>', 25)->where('age', '<', 35)->select();

        $this->assertCount(2, $users);
        $names = array_map(fn($u) => $u->name, $users);
        $this->assertContains('Bob', $names);
        $this->assertContains('Eve', $names);
    }

    public function testOrderByAsc(): void
    {
        $this->seedUsers();

        $users = User::order('age', 'ASC')->select();

        $this->assertSame('Dave', $users[0]->name);
        $this->assertSame('Alice', $users[1]->name);
    }

    public function testOrderByDesc(): void
    {
        $this->seedUsers();

        $users = User::order('age', 'DESC')->select();

        $this->assertSame('Charlie', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
    }

    public function testLimitAndOffset(): void
    {
        $this->seedUsers();

        $page1 = User::order('id', 'ASC')->limit(2)->offset(0)->select();
        $page2 = User::order('id', 'ASC')->limit(2)->offset(2)->select();

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotSame($page1[0]->id, $page2[0]->id);
    }

    public function testWhereIn(): void
    {
        $this->seedUsers();

        $users = User::whereIn('name', ['Alice', 'Charlie'])->select();

        $this->assertCount(2, $users);
    }

    public function testWhereNotIn(): void
    {
        $this->seedUsers();

        $users = User::whereNotIn('name', ['Alice', 'Charlie'])->select();

        $this->assertCount(3, $users);
    }

    public function testWhereBetween(): void
    {
        $this->seedUsers();

        $users = User::whereBetween('age', 25, 30)->select();

        $this->assertCount(3, $users);
    }

    public function testWhereNull(): void
    {
        $user = new User();
        $user->name = 'NullEmail';
        $user->email = '';
        $user->insert();

        $found = User::where('email', '=', '')->find();
        $this->assertNotNull($found);
        $this->assertSame('NullEmail', $found->name);
    }

    public function testFirst(): void
    {
        $this->seedUsers();

        $first = User::where('age', '>', 25)->find();

        $this->assertNotNull($first);
        $this->assertInstanceOf(User::class, $first);
    }

    public function testValue(): void
    {
        $this->seedUsers();

        $name = User::where('id', '=', 1)->value('name');

        $this->assertSame('Alice', $name);
    }

    public function testColumn(): void
    {
        $this->seedUsers();

        $names = User::order('id', 'ASC')->column('name');

        $this->assertCount(5, $names);
        $this->assertSame('Alice', $names[0]);
    }
}
