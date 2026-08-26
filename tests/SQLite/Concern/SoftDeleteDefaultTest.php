<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Concern;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\SoftDeleteDefaultUser;

class SoftDeleteDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();
        $this->createTable('CREATE TABLE test_soft_default_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            deleted_time TEXT NOT NULL DEFAULT "0"
        )');
    }

    private function seedUsers(): void
    {
        foreach (['Alice', 'Bob', 'Charlie'] as $name) {
            $user = new SoftDeleteDefaultUser();
            $user->name = $name;
            $user->insert();
        }
    }

    public function testSelectExcludesSoftDeleted(): void
    {
        $this->seedUsers();

        SoftDeleteDefaultUser::where('id', '=', 2)->find()->delete();

        $users = SoftDeleteDefaultUser::select();

        $this->assertCount(2, $users);
    }

    public function testDeleteSetsNonDefaultValue(): void
    {
        $this->seedUsers();

        $user = SoftDeleteDefaultUser::where('id', '=', 1)->find();
        $user->delete();

        $conn = DB::connect();
        $rows = $conn->query('SELECT deleted_time FROM test_soft_default_user WHERE id = 1');
        $this->assertSame('1', $rows[0]['deleted_time']);
    }

    public function testWithTrashedIncludesSoftDeleted(): void
    {
        $this->seedUsers();

        SoftDeleteDefaultUser::where('id', '=', 2)->find()->delete();

        $users = SoftDeleteDefaultUser::withTrashed()->select();

        $this->assertCount(3, $users);
    }

    public function testOnlyTrashedReturnsOnlySoftDeleted(): void
    {
        $this->seedUsers();

        SoftDeleteDefaultUser::where('id', '=', 1)->find()->delete();
        SoftDeleteDefaultUser::where('id', '=', 3)->find()->delete();

        $trashed = SoftDeleteDefaultUser::onlyTrashed()->select();

        $this->assertCount(2, $trashed);
    }

    public function testRestoreSetsDefaultBack(): void
    {
        $this->seedUsers();

        $user = SoftDeleteDefaultUser::where('id', '=', 1)->find();
        $user->delete();
        $user->restore();

        $conn = DB::connect();
        $rows = $conn->query('SELECT deleted_time FROM test_soft_default_user WHERE id = 1');
        $this->assertSame('0', $rows[0]['deleted_time']);
    }

    public function testSelectAfterRestore(): void
    {
        $this->seedUsers();

        $user = SoftDeleteDefaultUser::where('id', '=', 1)->find();
        $user->delete();
        $user->restore();

        $users = SoftDeleteDefaultUser::select();
        $this->assertCount(3, $users);
    }

    public function testForceDeleteHardDeletes(): void
    {
        $this->seedUsers();

        $user = SoftDeleteDefaultUser::where('id', '=', 2)->find();
        $user->forceDelete();

        $found = SoftDeleteDefaultUser::withTrashed()->where('id', '=', 2)->find();
        $this->assertNull($found);
    }

    public function testFindExcludesSoftDeleted(): void
    {
        $this->seedUsers();

        SoftDeleteDefaultUser::where('id', '=', 1)->find()->delete();

        $found = SoftDeleteDefaultUser::where('id', '=', 1)->find();
        $this->assertNull($found);
    }

    public function testUpdateExcludesSoftDeleted(): void
    {
        $this->seedUsers();

        SoftDeleteDefaultUser::where('id', '=', 1)->find()->delete();

        $affected = SoftDeleteDefaultUser::where('name', '=', 'Alice')->update(['name' => 'AliceUpdated']);
        $this->assertSame(0, $affected);
    }
}
