<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Query;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;

class CrudTest extends TestCase
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

    public function testInsertReturnsLastInsertId(): void
    {
        $id = DB::table('test_items')->insert([
            'name' => 'New Item',
            'price' => 19.99,
            'category' => 'misc',
        ]);
        $this->assertGreaterThan(0, $id);
        $row = DB::table('test_items')->where('id', '=', $id)->find();
        $this->assertSame('New Item', $row['name']);
    }

    public function testInsertAllInsertsMultipleRows(): void
    {
        $count = DB::table('test_items')->insertAll([
            ['name' => 'Item A', 'price' => 1.00, 'category' => 'x'],
            ['name' => 'Item B', 'price' => 2.00, 'category' => 'y'],
        ]);
        $this->assertSame(2, $count);
        $all = DB::table('test_items')->select();
        $this->assertCount(5, $all);
    }

    public function testInsertAllEmptyArrayReturnsZero(): void
    {
        $count = DB::table('test_items')->insertAll([]);
        $this->assertSame(0, $count);
    }

    public function testUpdateWithWhere(): void
    {
        $affected = DB::table('test_items')->where('id', '=', 1)->update(['price' => 12.99]);
        $this->assertSame(1, $affected);
        $row = DB::table('test_items')->where('id', '=', 1)->find();
        $this->assertEqualsWithDelta(12.99, $row['price'], 0.01);
    }

    public function testUpdateReturnsAffectedRowsCount(): void
    {
        $affected = DB::table('test_items')->where('id', '=', 999)->update(['price' => 0]);
        $this->assertSame(0, $affected);
    }

    public function testDeleteWithWhere(): void
    {
        $affected = DB::table('test_items')->where('id', '=', 3)->delete();
        $this->assertSame(1, $affected);
        $remaining = DB::table('test_items')->select();
        $this->assertCount(2, $remaining);
    }

    public function testDeleteReturnsAffectedRowsCount(): void
    {
        $affected = DB::table('test_items')->where('id', '=', 999)->delete();
        $this->assertSame(0, $affected);
    }

    public function testCursorIteratesAllRows(): void
    {
        $rows = iterator_to_array(DB::table('test_items')->order('id', 'ASC')->cursor());
        $this->assertCount(3, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
        $this->assertSame('Widget B', $rows[1]['name']);
        $this->assertSame('Gadget', $rows[2]['name']);
    }

    public function testCursorWithWhereFiltersRows(): void
    {
        $rows = iterator_to_array(
            DB::table('test_items')->where('category', '=', 'tools')->cursor()
        );
        $this->assertCount(2, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
        $this->assertSame('Widget B', $rows[1]['name']);
    }
}
