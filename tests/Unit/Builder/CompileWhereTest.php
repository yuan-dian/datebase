<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Db\Expression\Raw;
use yuandian\Database\Db\Expression\QueryState;
use yuandian\Database\Db\Expression\WhereCondition;
use yuandian\Database\Db\Expression\WhereGroup;

class CompileWhereTest extends TestCase
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

    private function compileWhere(QueryState $s): array
    {
        $s->table = 'test_table';
        $compiled = $this->builder->compileSelect($s);
        return [$compiled->statement, $compiled->bind];
    }

    public function testEqualityOperators(): void
    {
        $tests = [
            ['=',  1, '"test_table" WHERE "id" = ?', [1]],
            ['<>', 1, '"test_table" WHERE "id" <> ?', [1]],
            ['>',  1, '"test_table" WHERE "id" > ?', [1]],
            ['>=', 1, '"test_table" WHERE "id" >= ?', [1]],
            ['<',  1, '"test_table" WHERE "id" < ?', [1]],
            ['<=', 1, '"test_table" WHERE "id" <= ?', [1]],
        ];
        foreach ($tests as [$op, $val, $expectedSql, $expectedBind]) {
            $s = $this->state();
            $s->where->add('AND', new WhereCondition('id', $op, $val));
            [$sql, $bind] = $this->compileWhere($s);
            $this->assertSame('SELECT * FROM ' . $expectedSql, $sql, "Operator {$op} failed");
            $this->assertSame($expectedBind, $bind, "Operator {$op} bind failed");
        }
    }

    public function testLikeAndNotLike(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('name', 'LIKE', '%test%'));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "name" LIKE ?', $sql);
        $this->assertSame(['%test%'], $bind);

        $s = $this->state();
        $s->where->add('AND', new WhereCondition('name', 'NOT LIKE', '%admin%'));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "name" NOT LIKE ?', $sql);
        $this->assertSame(['%admin%'], $bind);
    }

    public function testInAndNotIn(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', 'IN', [1, 2, 3]));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "id" IN (?, ?, ?)', $sql);
        $this->assertSame([1, 2, 3], $bind);

        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', 'NOT IN', [4, 5]));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "id" NOT IN (?, ?)', $sql);
        $this->assertSame([4, 5], $bind);
    }

    public function testBetweenAndNotBetween(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('age', 'BETWEEN', [18, 30]));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "age" BETWEEN ? AND ?', $sql);
        $this->assertSame([18, 30], $bind);

        $s = $this->state();
        $s->where->add('AND', new WhereCondition('age', 'NOT BETWEEN', [10, 20]));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "age" NOT BETWEEN ? AND ?', $sql);
        $this->assertSame([10, 20], $bind);
    }

    public function testIsNullAndIsNotNull(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('email', 'NULL', ''));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "email" IS NULL', $sql);
        $this->assertSame([], $bind);

        $s = $this->state();
        $s->where->add('AND', new WhereCondition('email', 'NOT NULL', ''));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "email" IS NOT NULL', $sql);
        $this->assertSame([], $bind);
    }

    public function testExpExpression(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', 'EXP', '> 5'));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE ( "id" > 5 )', $sql);
        $this->assertSame([], $bind);
    }

    public function testExpExpressionRaw(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', 'EXP', new Raw('> 5')));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE ( "id" > 5 )', $sql);
        $this->assertSame([], $bind);
    }

    public function testColumnComparison(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('a', 'COLUMN', ['>', 'b']));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE ( "a" > "b" )', $sql);
        $this->assertSame([], $bind);
    }

    public function testColumnComparisonDefaultEquals(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('first_name', 'COLUMN', 'last_name'));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "first_name" = "last_name"', $sql);
        $this->assertSame([], $bind);
    }

    public function testSubqueryInWhere(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('id', '=', function ($q) {
            $q->table('other_table')->where('status', '=', 1);
        }));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame(
            'SELECT * FROM "test_table" WHERE "id" = ( SELECT * FROM "other_table" WHERE "status" = ? )',
            $sql
        );
        $this->assertSame([1], $bind);
    }

    public function testWhereGroupNesting(): void
    {
        $subGroup = new WhereGroup();
        $subGroup->add('AND', new WhereCondition('age', '>', 18));
        $subGroup->add('AND', new WhereCondition('age', '<', 10));

        $s = $this->state();
        $s->where->add('AND', new WhereCondition('', 'GROUP', $subGroup));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame(
            'SELECT * FROM "test_table" WHERE ( "age" > ? AND "age" < ? )',
            $sql
        );
        $this->assertSame([18, 10], $bind);
    }

    public function testWhereGroupNestedWithOuterConditions(): void
    {
        $subGroup = new WhereGroup();
        $subGroup->add('AND', new WhereCondition('a', '=', 1));
        $subGroup->add('AND', new WhereCondition('b', '=', 2));

        $s = $this->state();
        $s->where->add('AND', new WhereCondition('status', '=', 'active'));
        $s->where->add('AND', new WhereCondition('', 'GROUP', $subGroup));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame(
            'SELECT * FROM "test_table" WHERE "status" = ? AND ( "a" = ? AND "b" = ? )',
            $sql
        );
        $this->assertSame(['active', 1, 2], $bind);
    }

    public function testRawWhereCondition(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('', 'RAW', new Raw('age > ?', [18])));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE age > ?', $sql);
        $this->assertSame([18], $bind);
    }

    public function testCompareWithRawValue(): void
    {
        $s = $this->state();
        $s->where->add('AND', new WhereCondition('created_at', '>', new Raw('NOW()')));
        [$sql, $bind] = $this->compileWhere($s);
        $this->assertSame('SELECT * FROM "test_table" WHERE "created_at" > NOW()', $sql);
        $this->assertSame([], $bind);
    }
}
