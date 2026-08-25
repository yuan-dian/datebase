<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Facade;

use PHPUnit\Framework\TestCase;
use yuandian\Container\Container;
use yuandian\Database\DbManager;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;

class DbFacadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        Container::getInstance()->instanceGlobal(DbManager::class, new DbManager());
        DB::setConfig(static::$sqliteConfig);
    }

    protected function tearDown(): void
    {
        DB::reset();
        parent::tearDown();
    }

    // ======================== 配置代理 ========================

    public function testSetConfigViaFacade(): void
    {
        $config = ['default' => 'sqlite', 'connections' => ['sqlite' => ['type' => 'sqlite', 'database' => ':memory:']]];
        DB::setConfig($config);
        $this->assertTrue(true);
    }

    public function testGetConfigViaFacade(): void
    {
        DB::setConfig(['default' => 'sqlite']);
        $value = DB::getConfig('default');
        $this->assertSame('sqlite', $value);
    }

    // ======================== 连接代理 ========================

    public function testConnectViaFacade(): void
    {
        $conn = DB::connect();
        $this->assertInstanceOf(\yuandian\Database\Db\Connection::class, $conn);
    }

    public function testConnectReturnsSameInstance(): void
    {
        $a = DB::connect();
        $b = DB::connect();
        $this->assertSame($a, $b);
    }

    // ======================== table 代理 ========================

    public function testTableViaFacade(): void
    {
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        $query = DB::table('test_user');
        $this->assertInstanceOf(\yuandian\Database\Db\BaseQuery::class, $query);
    }

    public function testTableQueryViaFacade(): void
    {
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        DB::execute("INSERT INTO test_user (name) VALUES (?)", ['Facade']);

        $result = DB::table('test_user')->select();
        $this->assertCount(1, $result);
        $this->assertSame('Facade', $result[0]['name']);
    }

    // ======================== 事务代理 ========================

    public function testTransactionViaFacade(): void
    {
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        $result = DB::transaction(function () {
            DB::execute("INSERT INTO test_user (name) VALUES (?)", ['TxItem']);
            return DB::table('test_user')->select();
        });

        $this->assertCount(1, $result);
        $this->assertSame('TxItem', $result[0]['name']);
    }

    public function testTransactionRollbackViaFacade(): void
    {
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        try {
            DB::transaction(function () {
                DB::execute("INSERT INTO test_user (name) VALUES (?)", ['Fail']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
        }

        $rows = DB::table('test_user')->select();
        $this->assertCount(0, $rows);
    }

    public function testManualTransactionViaFacade(): void
    {
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        DB::startTrans();
        DB::execute("INSERT INTO test_user (name) VALUES (?)", ['Manual']);
        DB::commit();

        $rows = DB::table('test_user')->select();
        $this->assertCount(1, $rows);
    }

    // ======================== execute/query 代理 ========================

    public function testExecuteViaFacade(): void
    {
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        DB::execute("INSERT INTO test_user (name) VALUES (?)", ['Executed']);
        $rows = DB::query("SELECT * FROM test_user");

        $this->assertCount(1, $rows);
        $this->assertSame('Executed', $rows[0]['name']);
    }

    // ======================== getFacadeClass ========================

    public function testFacadeResolvesToDbManager(): void
    {
        $reflection = new \ReflectionMethod(DB::class, 'getFacadeClass');
        $reflection->setAccessible(true);

        $result = $reflection->invoke(null);
        $this->assertSame(DbManager::class, $result);
    }
}
