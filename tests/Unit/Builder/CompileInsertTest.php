<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Db\Expression\Raw;

class CompileInsertTest extends TestCase
{
    private \yuandian\Database\Db\Builder\Sqlite $builder;

    protected function setUp(): void
    {
        $conn = new Sqlite(['type' => 'sqlite', 'database' => ':memory:']);
        $this->builder = $conn->getBuilder();
    }

    public function testSimpleInsert(): void
    {
        $compiled = $this->builder->compileInsert('test_table', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);
        $this->assertSame('INSERT INTO "test_table" ("name", "email") VALUES (?, ?) ', $compiled->statement);
        $this->assertSame(['John', 'john@example.com'], $compiled->bind);
    }

    public function testInsertWithRaw(): void
    {
        $compiled = $this->builder->compileInsert('test_table', [
            'name' => 'John',
            'created_at' => new Raw('NOW()'),
        ]);
        $this->assertSame('INSERT INTO "test_table" ("name", "created_at") VALUES (?, NOW()) ', $compiled->statement);
        $this->assertSame(['John'], $compiled->bind);
    }

    public function testInsertAll(): void
    {
        $dataList = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ];
        $compiled = $this->builder->compileInsertAll('test_table', $dataList);
        $this->assertSame(
            'INSERT INTO "test_table" ("name", "email") VALUES (?, ?), (?, ?) ',
            $compiled->statement
        );
        $this->assertSame(['John', 'john@example.com', 'Jane', 'jane@example.com'], $compiled->bind);
    }

    public function testInsertAllEmptyReturnsEmpty(): void
    {
        $compiled = $this->builder->compileInsertAll('test_table', []);
        $this->assertSame('', $compiled->statement);
        $this->assertSame([], $compiled->bind);
    }

    public function testInsertWithComment(): void
    {
        $compiled = $this->builder->compileInsert('test_table', [
            'name' => 'John',
        ], 'user registration');
        $this->assertSame('INSERT INTO "test_table" ("name") VALUES (?)  /* user registration */', $compiled->statement);
        $this->assertSame(['John'], $compiled->bind);
    }
}
