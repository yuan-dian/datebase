<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Db;

use PHPUnit\Framework\TestCase;
use yuandian\Database\DbManager;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;

class DbManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();
    }

    // ======================== 配置 ========================

    public function testSetAndGetConfig(): void
    {
        $manager = new DbManager();
        $manager->setConfig(['default' => 'sqlite', 'foo' => 'bar']);

        $this->assertSame('sqlite', $manager->getConfig('default'));
        $this->assertSame('bar', $manager->getConfig('foo'));
    }

    public function testGetConfigDefault(): void
    {
        $manager = new DbManager();
        $this->assertSame('fallback', $manager->getConfig('missing', 'fallback'));
    }

    public function testGetConfigNullDefault(): void
    {
        $manager = new DbManager();
        $this->assertNull($manager->getConfig('missing'));
    }

    public function testGetAllConfig(): void
    {
        $manager = new DbManager();
        $manager->setConfig(['a' => 1, 'b' => 2]);

        $all = $manager->getConfig('');
        $this->assertSame(['a' => 1, 'b' => 2], $all);
    }

    // ======================== 连接 ========================

    public function testConnectReturnsConnection(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $conn = DB::connect();
        $this->assertInstanceOf(\yuandian\Database\Db\Connection::class, $conn);
    }

    public function testConnectCachesInstance(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $a = DB::connect();
        $b = DB::connect();
        $this->assertSame($a, $b);
    }

    public function testConnectForceNewInstance(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $a = DB::connect();
        $b = DB::connect(force: true);
        $this->assertNotSame($a, $b);
    }

    public function testConnectSpecificConnection(): void
    {
        $config = static::$sqliteConfig;
        $config['connections']['second'] = [
            'type' => 'sqlite',
            'database' => ':memory:',
        ];

        DB::setConfig($config);
        $a = DB::connect('sqlite');
        $b = DB::connect('second');
        $this->assertNotSame($a, $b);
    }

    public function testConnectUndefinedConnectionThrows(): void
    {
        DB::setConfig(static::$sqliteConfig);

        $this->expectException(\yuandian\Database\Exceptions\DbException::class);
        DB::connect('nonexistent');
    }

    // ======================== table ========================

    public function testTableReturnsQuery(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $query = DB::table('test_user');
        $this->assertInstanceOf(\yuandian\Database\Db\BaseQuery::class, $query);
    }

    public function testTableQueryIsFunctional(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        DB::execute("INSERT INTO test_user (name) VALUES (?)", ['Alice']);
        $result = DB::table('test_user')->select();
        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['name']);
    }

    // ======================== 事件 ========================

    public function testListenAndTrigger(): void
    {
        $manager = new DbManager();
        $received = [];
        $manager->listen('test_event', function ($val) use (&$received) {
            $received[] = $val;
        });

        $manager->trigger('test_event', 'hello');
        $manager->trigger('test_event', 'world');

        $this->assertSame(['hello', 'world'], $received);
    }

    public function testListenMultipleCallbacks(): void
    {
        $manager = new DbManager();
        $log = [];
        $manager->listen('event', function () use (&$log) { $log[] = 'a'; });
        $manager->listen('event', function () use (&$log) { $log[] = 'b'; });

        $manager->trigger('event');

        $this->assertSame(['a', 'b'], $log);
    }

    public function testTriggerWithoutListeners(): void
    {
        $manager = new DbManager();
        $manager->trigger('nonexistent');
        $this->assertTrue(true);
    }

    public function testGetListen(): void
    {
        $manager = new DbManager();
        $cb = function () {};
        $manager->listen('e1', $cb);

        $listeners = $manager->getListen();
        $this->assertArrayHasKey('e1', $listeners);
        $this->assertCount(1, $listeners['e1']);
    }

    // ======================== 日志 ========================

    public function testLogAndRetrieve(): void
    {
        $manager = new DbManager();
        $manager->log('test message', 'info');
        $manager->log('warning message', 'warning');

        $logs = $manager->getLog();
        $this->assertCount(2, $logs);
        $this->assertSame('test message', $logs[0]['message']);
        $this->assertSame('info', $logs[0]['level']);
        $this->assertSame('warning message', $logs[1]['message']);
        $this->assertSame('warning', $logs[1]['level']);
    }

    public function testLogHasTimestamp(): void
    {
        $manager = new DbManager();
        $manager->log('msg');

        $logs = $manager->getLog();
        $this->assertArrayHasKey('time', $logs[0]);
        $this->assertNotEmpty($logs[0]['time']);
    }

    public function testClearLog(): void
    {
        $manager = new DbManager();
        $manager->log('msg');
        $manager->clearLog();

        $this->assertEmpty($manager->getLog());
    }

    public function testMaxLogSizeTrimsOldEntries(): void
    {
        $manager = new DbManager();
        $manager->setMaxLogSize(3);

        for ($i = 0; $i < 5; $i++) {
            $manager->log("msg{$i}");
        }

        $logs = $manager->getLog();
        $this->assertCount(3, $logs);
        $this->assertSame('msg2', $logs[0]['message']);
        $this->assertSame('msg4', $logs[2]['message']);
    }

    public function testMaxLogSizeZeroUnlimited(): void
    {
        $manager = new DbManager();
        $manager->setMaxLogSize(0);

        for ($i = 0; $i < 100; $i++) {
            $manager->log("msg{$i}");
        }

        $this->assertCount(100, $manager->getLog());
    }

    // ======================== reset ========================

    public function testResetClearsEventsAndLog(): void
    {
        $manager = new DbManager();
        $manager->listen('test', function () {});
        $manager->log('msg');

        $manager->reset();

        $this->assertEmpty($manager->getListen());
        $this->assertEmpty($manager->getLog());
    }

    public function testResetPreservesConfig(): void
    {
        $manager = new DbManager();
        $manager->setConfig(['default' => 'sqlite', 'foo' => 'bar']);

        $manager->reset();

        $this->assertSame('sqlite', $manager->getConfig('default'));
        $this->assertSame('bar', $manager->getConfig('foo'));
    }

    // ======================== 事务 ========================

    public function testTransactionCommit(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        DB::transaction(function () {
            DB::execute("INSERT INTO test_user (name) VALUES (?)", ['InTx']);
        });

        $rows = DB::table('test_user')->select();
        $this->assertCount(1, $rows);
    }

    public function testTransactionRollback(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        try {
            DB::transaction(function () {
                DB::execute("INSERT INTO test_user (name) VALUES (?)", ['Fail']);
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
        }

        $rows = DB::table('test_user')->select();
        $this->assertCount(0, $rows);
    }

    public function testStartTransCommitManual(): void
    {
        DB::setConfig(static::$sqliteConfig);
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

    public function testStartTransRollbackManual(): void
    {
        DB::setConfig(static::$sqliteConfig);
        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');

        DB::startTrans();
        DB::execute("INSERT INTO test_user (name) VALUES (?)", ['Undo']);
        DB::rollback();

        $rows = DB::table('test_user')->select();
        $this->assertCount(0, $rows);
    }
}
