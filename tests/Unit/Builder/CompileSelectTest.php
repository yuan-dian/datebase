<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Db\Expression\Raw;
use yuandian\Database\Db\Expression\QueryState;
use yuandian\Database\Db\Expression\WhereCondition;

class CompileSelectTest extends TestCase
{
    private \yuandian\Database\Db\Builder\Sqlite $builder;

    protected function setUp(): void
    {
        $conn = new Sqlite(['type' => 'sqlite', 'database' => ':memory:']);
        $this->builder = $conn->getBuilder();
    }

    private function state(string $table = 'test_table'): QueryState
    {
        $s = new QueryState();
        $s->table = $table;
        return $s;
    }

    public function testSimpleSelect(): void
    {
        $compiled = $this->builder->compileSelect($this->state());
        $this->assertSame('SELECT * FROM "test_table"', $compiled->statement);
        $this->assertSame([], $compiled->bind);
    }

    public function testFieldSelection(): void
    {
        $s = $this->state();
        $s->field = ['id', 'name'];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT "id", "name" FROM "test_table"', $compiled->statement);
        $this->assertSame([], $compiled->bind);
    }

    public function testFieldAlias(): void
    {
        $s = $this->state();
        $s->field = ['id' => 'user_id'];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT "id" AS "user_id" FROM "test_table"', $compiled->statement);
        $this->assertSame([], $compiled->bind);
    }

    public function testSelectWithWhere(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', 1));
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "id" = ?', $compiled->statement);
        $this->assertSame([1], $compiled->bind);
    }

    public function testMultipleAndWhere(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', 1));
        $s->where->add('AND', new WhereCondition('name', '=', 'test'));
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "id" = ? AND "name" = ?', $compiled->statement);
        $this->assertSame([1, 'test'], $compiled->bind);
    }

    public function testOrWhere(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', 1));
        $s->where->add('OR', new WhereCondition('name', '=', 'test'));
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "id" = ? OR "name" = ?', $compiled->statement);
        $this->assertSame([1, 'test'], $compiled->bind);
    }

    public function testOrderByAsc(): void
    {
        $s = $this->state();
        $s->order[] = ['name', 'ASC'];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" ORDER BY "name" ASC', $compiled->statement);
    }

    public function testOrderByDesc(): void
    {
        $s = $this->state();
        $s->order[] = ['id', 'DESC'];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" ORDER BY "id" DESC', $compiled->statement);
    }

    public function testLimitAndOffset(): void
    {
        $s = $this->state();
        $s->limit = 10;
        $s->offset = 5;
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" LIMIT 10 OFFSET 5', $compiled->statement);
    }

    public function testGroupBy(): void
    {
        $s = $this->state();
        $s->group = ['status'];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" GROUP BY "status"', $compiled->statement);
    }

    public function testHaving(): void
    {
        $s = $this->state();
        $s->group = ['status'];
        $s->having = ['COUNT(*) > 5'];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" GROUP BY "status" HAVING COUNT(*) > 5', $compiled->statement);
    }

    public function testDistinct(): void
    {
        $s = $this->state();
        $s->distinct = true;
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT DISTINCT * FROM "test_table"', $compiled->statement);
    }

    public function testTableAlias(): void
    {
        $s = $this->state();
        $s->alias = 'u';
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table" AS "u"', $compiled->statement);
    }

    public function testDotNotationField(): void
    {
        $s = $this->state();
        $s->field = ['t.id', 't.name'];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT "t"."id", "t"."name" FROM "test_table"', $compiled->statement);
    }

    public function testRawFieldExpression(): void
    {
        $s = $this->state();
        $s->field = [new Raw('NOW()')];
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT NOW() FROM "test_table"', $compiled->statement);
        $this->assertSame([], $compiled->bind);
    }

    public function testComment(): void
    {
        $s = $this->state();
        $s->comment = 'test query';
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table"  /* test query */', $compiled->statement);
    }

    public function testLockForUpdate(): void
    {
        $s = $this->state();
        $s->lock = true;
        $compiled = $this->builder->compileSelect($s);
        $this->assertSame('SELECT * FROM "test_table"  FOR UPDATE', $compiled->statement);
    }

    public function testComplexSelect(): void
    {
        $s = $this->state('users');
        $s->alias = 'u';
        $s->field = ['id', 'name' => 'user_name'];
        $s->distinct = true;
        $s->where->add('AND', new WhereCondition('age', '>', 18));
        $s->where->add('OR', new WhereCondition('status', '=', 'active'));
        $s->group = ['status'];
        $s->having = ['COUNT(*) > 5'];
        $s->order[] = ['id', 'ASC'];
        $s->limit = 100;
        $s->offset = 0;
        $s->lock = true;
        $s->comment = 'complex query';

        $compiled = $this->builder->compileSelect($s);

        $this->assertSame(
            'SELECT DISTINCT "id", "name" AS "user_name" FROM "users" AS "u" WHERE "age" > ? OR "status" = ? GROUP BY "status" HAVING COUNT(*) > 5 ORDER BY "id" ASC LIMIT 100 OFFSET 0  FOR UPDATE /* complex query */',
            $compiled->statement
        );
        $this->assertSame([18, 'active'], $compiled->bind);
    }

    public function testDeleteWithWhere(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', 1));
        $compiled = $this->builder->compileDelete('test_table', $s);
        $this->assertSame('DELETE FROM "test_table" WHERE "id" = ?', $compiled->statement);
        $this->assertSame([1], $compiled->bind);
    }

    public function testDeleteWithoutWhere(): void
    {
        $compiled = $this->builder->compileDelete('test_table', $this->state());
        $this->assertSame('DELETE FROM "test_table"', $compiled->statement);
        $this->assertSame([], $compiled->bind);
    }
}
