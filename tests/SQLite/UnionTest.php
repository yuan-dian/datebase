<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;

class UnionTest extends TestCase
{
    use RefreshDatabase;

    private array $sqls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->sqls = [];
        DB::listen('sql', function (string $sql) {
            $this->sqls[] = $sql;
        });

        $conn = DB::connect();
        $conn->execute('DROP TABLE IF EXISTS union_a');
        $conn->execute('DROP TABLE IF EXISTS union_b');
        $conn->execute('CREATE TABLE union_a (id INTEGER PRIMARY KEY AUTOINCREMENT, val INTEGER)');
        $conn->execute('CREATE TABLE union_b (id INTEGER PRIMARY KEY AUTOINCREMENT, val INTEGER)');
        $conn->execute('INSERT INTO union_a (val) VALUES (3), (1), (5)');
        $conn->execute('INSERT INTO union_b (val) VALUES (2), (4), (6)');
    }

    protected function tearDown(): void
    {
        $this->sqls = [];
        parent::tearDown();
    }

    private function lastSql(): string
    {
        return end($this->sqls) ?: '';
    }

    public function testUnionOrderLimitTemplateOrder(): void
    {
        $rows = DB::table('union_a')
            ->union(DB::table('union_b'))
            ->order('val', 'asc')
            ->limit(3)
            ->select();

        $sql = $this->lastSql();
        $posUnion = strpos($sql, 'UNION');
        $posOrder = strpos($sql, 'ORDER BY');

        $this->assertNotFalse($posUnion, 'SQL should contain UNION');
        $this->assertNotFalse($posOrder, 'SQL should contain ORDER BY');
        $this->assertLessThan($posOrder, $posUnion, 'UNION should appear before ORDER BY in SQL');

        $this->assertStringContainsString('LIMIT 3', $sql, 'Overall LIMIT 3 should appear after UNION');

        $vals = array_column($rows, 'val');
        $this->assertCount(3, $vals, 'Should return exactly 3 rows');
        $this->assertSame([1, 2, 3], $vals, 'Result should be sorted val=1,2,3');
    }

    public function testSubqueryOrderLimitPreserved(): void
    {
        $rows = DB::table('union_a')
            ->union(
                DB::table('union_b')
                    ->where('id', '>', 1)
                    ->order('val', 'desc')
                    ->limit(1)
            )
            ->select();

        $sql = $this->lastSql();

        $this->assertStringContainsString('ORDER BY', $sql, 'Subquery internal ORDER BY should be preserved');

        $this->assertStringContainsString('( SELECT', $sql, 'Subquery with ORDER/LIMIT should be wrapped in derived table');

        $selectCount = substr_count($sql, 'SELECT');
        $this->assertSame(3, $selectCount, 'Should have three SELECT statements (main + derived table outer + subquery inner)');

        $this->assertCount(4, $rows, 'Should return 4 rows (3 from a + 1 from b limited)');
    }

    public function testNoUnionRegression(): void
    {
        $rows = DB::table('union_a')
            ->order('id', 'asc')
            ->limit(2)
            ->select();

        $this->assertCount(2, $rows, 'Should return 2 rows');
        $this->assertSame(1, $rows[0]['id'], 'First row id=1');
        $this->assertSame(2, $rows[1]['id'], 'Second row id=2');
    }

    public function testUnionTypeWhitelist(): void
    {
        $rows = DB::table('union_a')
            ->union(DB::table('union_b'), 'UNION; DROP TABLE union_a; --')
            ->select();

        $sql = $this->lastSql();
        $this->assertStringNotContainsString('DROP TABLE', $sql, 'Malicious type must not enter SQL');
        $this->assertSame(1, substr_count($sql, 'UNION'), 'Malicious type should fall back to single UNION');
        $this->assertCount(6, $rows, 'Result should be 6 rows (normal UNION)');

        $this->sqls = [];
        DB::table('union_a')->union(DB::table('union_b'), 'union all')->select();
        $sqlAll = $this->lastSql();
        $this->assertStringContainsString('UNION ALL', $sqlAll, "Lowercase 'union all' should normalize to UNION ALL");
    }

    public function testUnionWithStringBranch(): void
    {
        $rows = DB::table('union_a')
            ->union('SELECT * FROM union_b')
            ->select();

        $sql = $this->lastSql();
        $this->assertStringContainsString('UNION SELECT * FROM union_b', $sql, 'String without ORDER/LIMIT should be directly concatenated');
        $this->assertCount(6, $rows, 'String UNION result should be 6 rows');

        $this->sqls = [];
        $rowsWithLimit = DB::table('union_a')
            ->union('SELECT * FROM union_b ORDER BY val DESC LIMIT 1')
            ->select();

        $sqlWithLimit = $this->lastSql();
        $this->assertStringContainsString(
            'SELECT * FROM ( SELECT * FROM union_b ORDER BY val DESC LIMIT 1 ) AS t',
            $sqlWithLimit,
            'String with ORDER/LIMIT should be wrapped in derived table AS t'
        );
        $this->assertCount(4, $rowsWithLimit, 'String derived table result should be 4 rows (3 from a + 1 from b limited)');
    }

    public function testDerivedTableAlias(): void
    {
        DB::table('union_a')
            ->union(DB::table('union_b')->order('val', 'desc')->limit(2))
            ->select();

        $sql = $this->lastSql();
        $this->assertStringContainsString(') AS t', $sql, 'Subquery branch should have derived table with AS t alias');
    }
}
