<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Query;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;

class SelectTest extends TestCase
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

    public function testSelectReturnsAllRows(): void
    {
        $rows = DB::table('test_items')->select();
        $this->assertCount(3, $rows);
        $this->assertSame('Widget A', $rows[0]['name']);
        $this->assertSame('Widget B', $rows[1]['name']);
        $this->assertSame('Gadget', $rows[2]['name']);
    }

    public function testFindReturnsSingleRow(): void
    {
        $row = DB::table('test_items')->where('id', '=', 1)->find();
        $this->assertIsArray($row);
        $this->assertSame('Widget A', $row['name']);
        $this->assertSame(9.99, $row['price']);
    }

    public function testFindReturnsNullForNonExistent(): void
    {
        $row = DB::table('test_items')->where('id', '=', 999)->find();
        $this->assertNull($row);
    }

    public function testWhereFindFiltersCorrectly(): void
    {
        $row = DB::table('test_items')->where('name', '=', 'Gadget')->find();
        $this->assertIsArray($row);
        $this->assertSame(3, $row['id']);
        $this->assertSame(49.99, $row['price']);
    }

    public function testWhereChainWithAnd(): void
    {
        $rows = DB::table('test_items')
            ->where('category', '=', 'tools')
            ->where('price', '>', 15)
            ->select();
        $this->assertCount(1, $rows);
        $this->assertSame('Widget B', $rows[0]['name']);
    }

    public function testOrderByAsc(): void
    {
        $rows = DB::table('test_items')->order('price', 'ASC')->select();
        $this->assertSame('Widget A', $rows[0]['name']);
        $this->assertSame('Widget B', $rows[1]['name']);
        $this->assertSame('Gadget', $rows[2]['name']);
    }

    public function testOrderByDesc(): void
    {
        $rows = DB::table('test_items')->order('price', 'DESC')->select();
        $this->assertSame('Gadget', $rows[0]['name']);
        $this->assertSame('Widget B', $rows[1]['name']);
        $this->assertSame('Widget A', $rows[2]['name']);
    }

    public function testLimitAndOffsetPagination(): void
    {
        $page1 = DB::table('test_items')->order('id', 'ASC')->limit(2)->offset(0)->select();
        $this->assertCount(2, $page1);
        $this->assertSame('Widget A', $page1[0]['name']);
        $this->assertSame('Widget B', $page1[1]['name']);

        $page2 = DB::table('test_items')->order('id', 'ASC')->limit(2)->offset(2)->select();
        $this->assertCount(1, $page2);
        $this->assertSame('Gadget', $page2[0]['name']);
    }

    public function testValueReturnsSingleField(): void
    {
        $price = DB::table('test_items')->where('id', '=', 1)->value('price');
        $this->assertSame(9.99, $price);
    }

    public function testValueReturnsDefaultWhenNoResult(): void
    {
        $price = DB::table('test_items')->where('id', '=', 999)->value('price', 0.0);
        $this->assertSame(0.0, $price);
    }

    public function testColumnReturnsSingleColumnArray(): void
    {
        $names = DB::table('test_items')->order('id', 'ASC')->column('name');
        $this->assertSame(['Widget A', 'Widget B', 'Gadget'], $names);
    }

    public function testColumnWithKeyParameter(): void
    {
        $namesByName = DB::table('test_items')->order('id', 'ASC')->column('name', 'name');
        $this->assertSame([
            'Widget A' => 'Widget A',
            'Widget B' => 'Widget B',
            'Gadget' => 'Gadget',
        ], $namesByName);
    }

    public function testSelectWithSpecificFields(): void
    {
        $rows = DB::table('test_items')->field(['id', 'name'])->select();
        $this->assertCount(3, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayNotHasKey('price', $rows[0]);
        $this->assertArrayNotHasKey('category', $rows[0]);
    }

    public function testBuildSqlReturnsSqlString(): void
    {
        $sql = DB::table('test_items')->buildSql();
        $this->assertIsString($sql);
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('"test_items"', $sql);
    }

    public function testBuildSqlWithSubqueryWrapsInParentheses(): void
    {
        $sql = DB::table('test_items')->buildSql(true);
        $this->assertStringStartsWith('( ', $sql);
        $this->assertStringEndsWith(' )', $sql);
    }
}
