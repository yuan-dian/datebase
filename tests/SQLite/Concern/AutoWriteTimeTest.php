<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Concern;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\TimestampModel;
use yuandian\Database\Tests\Fixture\Model\CustomTimestampModel;
use yuandian\Database\Tests\Fixture\Model\DisableTimestampModel;

class AutoWriteTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->createTable('CREATE TABLE test_timestamp (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            create_time TEXT DEFAULT NULL,
            update_time TEXT DEFAULT NULL
        )');
        $this->createTable('CREATE TABLE test_custom_timestamp (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            created_at TEXT DEFAULT NULL,
            updated_at TEXT DEFAULT NULL
        )');
        $this->createTable('CREATE TABLE test_disable_timestamp (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');
    }

    public function testInsertSetsBothTimestamps(): void
    {
        $model = new TimestampModel();
        $model->name = 'Test';
        $model->insert();

        $found = TimestampModel::where('id', '=', $model->id)->find();
        $this->assertNotNull($found->createTime);
        $this->assertNotNull($found->updateTime);
        $this->assertSame($found->createTime, $found->updateTime);
    }

    public function testUpdateOnlyRefreshesUpdateTime(): void
    {
        $model = new TimestampModel();
        $model->name = 'Test';
        $model->insert();

        $createTime = $model->createTime;

        usleep(1100000);

        $model->name = 'Updated';
        $model->save();

        $found = TimestampModel::where('id', '=', $model->id)->find();
        $this->assertSame($createTime, $found->createTime);
        $this->assertNotSame($createTime, $found->updateTime);
    }

    public function testCustomColumnNames(): void
    {
        $model = new CustomTimestampModel();
        $model->name = 'Custom';
        $model->insert();

        $found = CustomTimestampModel::where('id', '=', $model->id)->find();
        $this->assertNotNull($found->createdAt);
        $this->assertNotNull($found->updatedAt);
    }

    public function testDisabledTimestamps(): void
    {
        $model = new DisableTimestampModel();
        $model->name = 'No Time';
        $model->insert();

        $conn = DB::connect();
        $rows = $conn->query('SELECT * FROM test_disable_timestamp WHERE id = ?', [$model->id]);
        $this->assertEmpty($rows[0]['create_time'] ?? null);
    }

    public function testExplicitValuesNotOverwritten(): void
    {
        $model = new TimestampModel();
        $model->name = 'Explicit';
        $model->createTime = '2020-01-01 00:00:00';
        $model->updateTime = '2020-01-01 00:00:00';
        $model->insert();

        $found = TimestampModel::where('id', '=', $model->id)->find();
        $this->assertSame('2020-01-01 00:00:00', $found->createTime);
    }

    public function testPropertySyncAfterInsert(): void
    {
        $model = new TimestampModel();
        $model->name = 'Sync';
        $model->insert();

        $this->assertNotNull($model->createTime);
        $this->assertNotNull($model->updateTime);
    }
}
