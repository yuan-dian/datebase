<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Model;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\User;

class AggregateTest extends TestCase
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
        foreach ([['Alice', 'alice@test.com', 25], ['Bob', 'bob@test.com', 30], ['Charlie', 'charlie@test.com', 35]] as [$name, $email, $age]) {
            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->age = $age;
            $user->insert();
        }
    }

    public function testCountAll(): void
    {
        $this->seedUsers();

        $count = User::count();

        $this->assertSame(3, $count);
    }

    public function testCountWithWhere(): void
    {
        $this->seedUsers();

        $count = User::where('age', '>', 25)->count();

        $this->assertSame(2, $count);
    }

    public function testSum(): void
    {
        $this->seedUsers();

        $sum = User::sum('age');

        $this->assertSame(90.0, $sum);
    }

    public function testAvg(): void
    {
        $this->seedUsers();

        $avg = User::avg('age');

        $this->assertEqualsWithDelta(30.0, $avg, 0.01);
    }

    public function testMin(): void
    {
        $this->seedUsers();

        $min = User::min('age');

        $this->assertSame(25, $min);
    }

    public function testMax(): void
    {
        $this->seedUsers();

        $max = User::max('age');

        $this->assertSame(35, $max);
    }

    public function testCountEmptyTable(): void
    {
        $count = User::count();

        $this->assertSame(0, $count);
    }
}
