<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Concern;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Model;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\EventUser;

class HasEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();
        $this->createTable('CREATE TABLE test_event_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            slug TEXT NOT NULL DEFAULT "",
            blocked INTEGER NOT NULL DEFAULT 0,
            deleted_time TEXT DEFAULT NULL
        )');
    }

    protected function tearDown(): void
    {
        Model::resetEvents();
        EventUser::resetEvents();
        parent::tearDown();
    }

    public function testOnBeforeInsertAutoGeneratesSlug(): void
    {
        $user = new EventUser();
        $user->name = 'Hello World';
        $user->insert();

        $found = EventUser::where('id', '=', $user->id)->find();
        $this->assertSame('hello-world', $found->slug);
    }

    public function testOnBeforeDeleteBlocksProtectedUser(): void
    {
        $user = new EventUser();
        $user->name = 'Protected';
        $user->blocked = 1;
        $user->insert();

        $result = $user->delete();

        $this->assertFalse($result);

        $found = EventUser::where('id', '=', $user->id)->find();
        $this->assertNotNull($found);
    }

    public function testOnBeforeDeleteAllowsUnblockedUser(): void
    {
        $user = new EventUser();
        $user->name = 'Unprotected';
        $user->blocked = 0;
        $user->insert();

        $result = $user->delete();

        $this->assertTrue($result);

        $found = EventUser::where('id', '=', $user->id)->find();
        $this->assertNull($found);
    }

    public function testGlobalListenerCanBlockInsert(): void
    {
        $called = false;
        Model::listen('beforeInsert', function (EventUser $user) use (&$called) {
            $called = true;
            if ($user->name === 'Blocked') {
                return false;
            }
            return true;
        });

        $user = new EventUser();
        $user->name = 'Blocked';
        $result = $user->insert();

        $this->assertTrue($called);
        $this->assertFalse($result);

        Model::resetEvents();
    }

    public function testGlobalListenerAllowsInsert(): void
    {
        Model::listen('beforeInsert', fn() => true);

        $user = new EventUser();
        $user->name = 'Allowed';
        $result = $user->insert();

        $this->assertTrue($result);
        $this->assertGreaterThan(0, $user->id);

        Model::resetEvents();
    }

    public function testModelListenerCanBlockInsert(): void
    {
        EventUser::modelListen('beforeInsert', function (EventUser $user) {
            if (str_starts_with($user->name, 'NO:')) {
                return false;
            }
            return true;
        });

        $user = new EventUser();
        $user->name = 'NO:blocked';
        $result = $user->insert();

        $this->assertFalse($result);

        Model::resetEvents();
    }

    public function testModelListenerOnlyAffectsSameModel(): void
    {
        EventUser::modelListen('beforeInsert', function () {
            return false;
        });

        $user = new EventUser();
        $user->name = 'Should Fail';
        $result = $user->insert();

        $this->assertFalse($result);

        Model::resetEvents();
    }

    public function testResetEventsClearsListeners(): void
    {
        $called = false;
        Model::listen('beforeInsert', function () use (&$called) {
            $called = true;
            return true;
        });

        Model::resetEvents();

        $user = new EventUser();
        $user->name = 'After Reset';
        $user->insert();

        $this->assertFalse($called);
    }

    public function testAfterInsertEventFires(): void
    {
        $afterCalled = false;
        Model::listen('afterInsert', function () use (&$afterCalled) {
            $afterCalled = true;
            return true;
        });

        $user = new EventUser();
        $user->name = 'Event Test';
        $user->insert();

        $this->assertTrue($afterCalled);

        Model::resetEvents();
    }
}
