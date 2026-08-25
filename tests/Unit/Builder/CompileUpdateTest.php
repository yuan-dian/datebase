<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Db\Expression\Express;
use yuandian\Database\Db\Expression\Raw;
use yuandian\Database\Db\Expression\QueryState;
use yuandian\Database\Db\Expression\WhereCondition;

class CompileUpdateTest extends TestCase
{
    private \yuandian\Database\Db\Builder\Sqlite $builder;

    protected function setUp(): void
    {
        $conn = new Sqlite(['type' => 'sqlite', 'database' => ':memory:']);
        $this->builder = $conn->getBuilder();
    }

    private function state(): QueryState
    {
        return new QueryState();
    }

    public function testSimpleUpdateWithWhere(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', 1));
        $compiled = $this->builder->compileUpdate('test_table', ['name' => 'Jane'], $s);
        $this->assertSame('UPDATE "test_table" SET "name" = ? WHERE "id" = ?', $compiled->statement);
        $this->assertSame(['Jane', 1], $compiled->bind);
    }

    public function testUpdateWithRawExpression(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', 1));
        $compiled = $this->builder->compileUpdate('test_table', [
            'updated_at' => new Raw('NOW()'),
        ], $s);
        $this->assertSame('UPDATE "test_table" SET "updated_at" = NOW() WHERE "id" = ?', $compiled->statement);
        $this->assertSame([1], $compiled->bind);
    }

    public function testUpdateWithExpress(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', 1));
        $compiled = $this->builder->compileUpdate('test_table', [
            'age' => new Express('+', 1),
            'score' => new Express('-', 5),
        ], $s);
        $this->assertSame(
            'UPDATE "test_table" SET "age" = "age" + 1, "score" = "score" - 5 WHERE "id" = ?',
            $compiled->statement
        );
        $this->assertSame([1], $compiled->bind);
    }

    public function testUpdateWithoutWhere(): void
    {
        $compiled = $this->builder->compileUpdate('test_table', ['status' => 'inactive'], $this->state());
        $this->assertSame('UPDATE "test_table" SET "status" = ?', $compiled->statement);
        $this->assertSame(['inactive'], $compiled->bind);
    }
}
