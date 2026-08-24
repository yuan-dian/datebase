<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Query;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;

class AggregateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::reset();
        DB::setConfig([
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'type' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ]);
        $conn = DB::connect();
        $conn->execute('CREATE TABLE test_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL DEFAULT "", price REAL NOT NULL DEFAULT 0, category TEXT NOT NULL DEFAULT "")');
        $conn->execute("INSERT INTO test_items (name, price, category) VALUES ('Widget A', 9.99, 'tools'), ('Widget B', 29.99, 'tools'), ('Gadget', 49.99, 'electronics')");
    }

    protected function tearDown(): void
    {
        DB::reset();
        parent::tearDown();
    }

    public function testCountAllRows(): void
    {
        $count = DB::table('test_items')->count();
        $this->assertSame(3, $count);
    }

    public function testCountWithWhere(): void
    {
        $count = DB::table('test_items')->where('category', '=', 'tools')->count();
        $this->assertSame(2, $count);
    }

    public function testSum(): void
    {
        $sum = DB::table('test_items')->sum('price');
        $this->assertEqualsWithDelta(89.97, $sum, 0.01);
    }

    public function testAvg(): void
    {
        $avg = DB::table('test_items')->avg('price');
        $this->assertEqualsWithDelta(29.99, $avg, 0.01);
    }

    public function testMin(): void
    {
        $min = DB::table('test_items')->min('price');
        $this->assertEqualsWithDelta(9.99, (float)$min, 0.01);
    }

    public function testMax(): void
    {
        $max = DB::table('test_items')->max('price');
        $this->assertEqualsWithDelta(49.99, (float)$max, 0.01);
    }

    public function testCountWithGroupBy(): void
    {
        $count = DB::table('test_items')->groupBy('category')->count();
        $this->assertSame(2, $count);
    }

    public function testSumWithWhere(): void
    {
        $sum = DB::table('test_items')->where('category', '=', 'tools')->sum('price');
        $this->assertEqualsWithDelta(39.98, $sum, 0.01);
    }
}
