<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Concern;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\SoftUser;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();
        $this->createTable('CREATE TABLE test_soft_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            deleted_time TEXT DEFAULT NULL
        )');
    }

    private function seedUsers(): void
    {
        foreach (['Alice', 'Bob', 'Charlie'] as $name) {
            $user = new SoftUser();
            $user->name = $name;
            $user->insert();
        }
    }

    public function testSelectExcludesSoftDeleted(): void
    {
        $this->seedUsers();

        SoftUser::where('id', '=', 2)->find()->delete();

        $users = SoftUser::select();

        $this->assertCount(2, $users);
    }

    public function testWithTrashedIncludesSoftDeleted(): void
    {
        $this->seedUsers();

        SoftUser::where('id', '=', 2)->find()->delete();

        $users = SoftUser::withTrashed()->select();

        $this->assertCount(3, $users);
    }

    public function testOnlyTrashedReturnsOnlySoftDeleted(): void
    {
        $this->seedUsers();

        SoftUser::where('id', '=', 1)->find()->delete();
        SoftUser::where('id', '=', 3)->find()->delete();

        $trashed = SoftUser::onlyTrashed()->select();

        $this->assertCount(2, $trashed);
    }

    public function testDeleteSetsTimestamp(): void
    {
        $this->seedUsers();

        $user = SoftUser::where('id', '=', 1)->find();
        $user->delete();

        $conn = DB::connect();
        $rows = $conn->query('SELECT deleted_time FROM test_soft_user WHERE id = 1');
        $this->assertNotNull($rows[0]['deleted_time']);
    }

    public function testRestoreClearsTimestamp(): void
    {
        $this->seedUsers();

        $user = SoftUser::where('id', '=', 1)->find();
        $user->delete();
        $user->restore();

        $conn = DB::connect();
        $rows = $conn->query('SELECT deleted_time FROM test_soft_user WHERE id = 1');
        $this->assertNull($rows[0]['deleted_time']);
    }

    public function testRestoreSetsExistsTrue(): void
    {
        $this->seedUsers();

        $user = SoftUser::where('id', '=', 1)->find();
        $user->delete();
        $this->assertFalse($user->exists());

        $user->restore();
        $this->assertTrue($user->exists());
    }

    public function testForceDeleteHardDeletes(): void
    {
        $this->seedUsers();

        $user = SoftUser::where('id', '=', 2)->find();
        $user->forceDelete();

        $found = SoftUser::withTrashed()->where('id', '=', 2)->find();
        $this->assertNull($found);
    }

    public function testSelectAfterRestore(): void
    {
        $this->seedUsers();

        $user = SoftUser::where('id', '=', 1)->find();
        $user->delete();
        $user->restore();

        $users = SoftUser::select();
        $this->assertCount(3, $users);
    }
}
