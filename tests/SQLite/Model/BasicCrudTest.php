<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Model;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\User;

class BasicCrudTest extends TestCase
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

    public function testInsertSetsIdAndExists(): void
    {
        $user = new User();
        $user->name = 'Alice';
        $user->email = 'alice@test.com';
        $user->age = 25;

        $result = $user->insert();

        $this->assertTrue($result);
        $this->assertTrue($user->exists());
        $this->assertGreaterThan(0, $user->id);
    }

    public function testSaveRoutesToInsertForNewModel(): void
    {
        $user = new User();
        $user->name = 'Bob';
        $user->email = 'bob@test.com';

        $result = $user->save();

        $this->assertTrue($result);
        $this->assertTrue($user->exists());
    }

    public function testSaveRoutesToUpdateForExistingModel(): void
    {
        $user = new User();
        $user->name = 'Charlie';
        $user->email = 'charlie@test.com';
        $user->insert();

        $user->name = 'Charles';
        $result = $user->save();

        $this->assertTrue($result);

        $found = User::where('id', '=', $user->id)->find();
        $this->assertSame('Charles', $found->name);
    }

    public function testUpdateOnlySavesDirtyFields(): void
    {
        $user = new User();
        $user->name = 'Dave';
        $user->email = 'dave@test.com';
        $user->age = 30;
        $user->insert();

        $found = User::where('id', '=', $user->id)->find();
        $found->age = 31;
        $found->update();

        $reloaded = User::where('id', '=', $user->id)->find();
        $this->assertSame('Dave', $reloaded->name);
        $this->assertSame('dave@test.com', $reloaded->email);
        $this->assertSame(31, $reloaded->age);
    }

    public function testFindReturnsModel(): void
    {
        $user = new User();
        $user->name = 'Eve';
        $user->email = 'eve@test.com';
        $user->insert();

        $found = User::where('id', '=', $user->id)->find();

        $this->assertNotNull($found);
        $this->assertSame('Eve', $found->name);
        $this->assertSame('eve@test.com', $found->email);
    }

    public function testFindReturnsNullForMissing(): void
    {
        $found = User::where('id', '=', 99999)->find();
        $this->assertNull($found);
    }

    public function testSelectReturnsAll(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $user = new User();
            $user->name = $name;
            $user->email = strtolower($name) . '@test.com';
            $user->insert();
        }

        $users = User::select();

        $this->assertCount(3, $users);
        $this->assertInstanceOf(User::class, $users[0]);
    }

    public function testDeleteRemovesModel(): void
    {
        $user = new User();
        $user->name = 'Frank';
        $user->email = 'frank@test.com';
        $user->insert();

        $result = $user->delete();

        $this->assertTrue($result);
        $this->assertFalse($user->exists());

        $found = User::where('id', '=', $user->id)->find();
        $this->assertNull($found);
    }

    public function testDeleteReturnsFalseWhenNoPk(): void
    {
        $user = new User();
        $user->name = 'Ghost';

        $result = $user->delete();

        $this->assertFalse($result);
    }

    public function testToArray(): void
    {
        $user = new User();
        $user->name = 'Helen';
        $user->email = 'helen@test.com';
        $user->age = 28;
        $user->insert();

        $found = User::where('id', '=', $user->id)->find();
        $array = $found->toArray();

        $this->assertSame('Helen', $array['name']);
        $this->assertSame('helen@test.com', $array['email']);
        $this->assertSame(28, $array['age']);
    }

    public function testToJson(): void
    {
        $user = new User();
        $user->name = 'Ivan';
        $user->email = 'ivan@test.com';
        $user->insert();

        $found = User::where('id', '=', $user->id)->find();
        $json = $found->toJson();

        $decoded = json_decode($json, true);
        $this->assertSame('Ivan', $decoded['name']);
    }
}
