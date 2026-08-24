<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Query;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Raw;
use yuandian\Database\Facade\DB;

class WhereTest extends TestCase
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

    public function testWhereEqual(): void
    {
        $rows = DB::table('test_items')->where('name', '=', 'Gadget')->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Gadget', $rows[0]['name']);
    }

    public function testWhereNotEqual(): void
    {
        $rows = DB::table('test_items')->where('category', '<>', 'tools')->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Gadget', $rows[0]['name']);
    }

    public function testWhereGreaterThan(): void
    {
        $rows = DB::table('test_items')->where('price', '>', 30)->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Gadget', $rows[0]['name']);
    }

    public function testWhereGreaterThanOrEqual(): void
    {
        $rows = DB::table('test_items')->where('price', '>=', 29.99)->select();
        $this->assertCount(2, $rows);
    }

    public function testWhereLessThan(): void
    {
        $rows = DB::table('test_items')->where('price', '<', 15)->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
    }

    public function testWhereLessThanOrEqual(): void
    {
        $rows = DB::table('test_items')->where('price', '<=', 29.99)->select();
        $this->assertCount(2, $rows);
    }

    public function testWhereIn(): void
    {
        $rows = DB::table('test_items')->whereIn('id', [1, 3])->select();
        $this->assertCount(2, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
        $this->assertSame('Gadget', $rows[1]['name']);
    }

    public function testWhereNotIn(): void
    {
        $rows = DB::table('test_items')->whereNotIn('id', [1])->select();
        $this->assertCount(2, $rows);
        $this->assertSame('Widget B', $rows[0]['name']);
        $this->assertSame('Gadget', $rows[1]['name']);
    }

    public function testWhereNull(): void
    {
        $rows = DB::table('test_items')->whereNull('name')->select();
        $this->assertCount(0, $rows);
    }

    public function testWhereNotNull(): void
    {
        $rows = DB::table('test_items')->whereNotNull('name')->select();
        $this->assertCount(3, $rows);
    }

    public function testWhereBetween(): void
    {
        $rows = DB::table('test_items')->whereBetween('price', 10, 30)->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Widget B', $rows[0]['name']);
    }

    public function testWhereLike(): void
    {
        $rows = DB::table('test_items')->whereLike('name', '%Widget%')->select();
        $this->assertCount(2, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
        $this->assertSame('Widget B', $rows[1]['name']);
    }

    public function testWhereRawWithBindParams(): void
    {
        $rows = DB::table('test_items')
            ->whereRaw('price > ? AND category = ?', [20, 'tools'])
            ->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Widget B', $rows[0]['name']);
    }

    public function testOrWhere(): void
    {
        $rows = DB::table('test_items')
            ->where('price', '<', 15)
            ->orWhere('category', '=', 'electronics')
            ->select();
        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('Widget A', $names);
        $this->assertContains('Gadget', $names);
    }

    public function testWhereGroupNestedParentheses(): void
    {
        $rows = DB::table('test_items')
            ->where('category', '=', 'tools')
            ->whereGroup(function ($q) {
                $q->where('price', '<', 15)
                    ->orWhere('price', '>', 40);
            })
            ->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
    }

    public function testWhereColumn(): void
    {
        DB::connect()->execute("INSERT INTO test_items (name, price, category) VALUES ('tools', 5.00, 'tools')");
        $rows = DB::table('test_items')
            ->whereColumn('name', 'category')
            ->select();
        $this->assertCount(1, $rows);
        $this->assertSame('tools', $rows[0]['name']);
    }

    public function testWhereWithRawExpression(): void
    {
        $rows = DB::table('test_items')
            ->where('price', '>', new Raw('10'))
            ->select();
        $this->assertCount(2, $rows);
    }

    public function testWhereWithClosureSubquery(): void
    {
        $rows = DB::table('test_items')
            ->where('id', '=', function ($sub) {
                $sub->table('test_items')->where('price', '<', 20)->field(['id']);
            })
            ->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
    }

    public function testInvalidOperatorThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DB::table('test_items')->where('id', 'INVALID', 1)->select();
    }
}
