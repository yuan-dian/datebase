<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Db\Builder\Oracle;
use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Db\QueryContext;
use yuandian\Database\Db\Expression\QueryState;

class OracleLimitTest extends TestCase
{
    private Oracle $builder;

    protected function setUp(): void
    {
        $conn = new Sqlite(['type' => 'sqlite', 'database' => ':memory:']);

        $context = new class($conn) implements QueryContext {
            public function __construct(private \yuandian\Database\Db\Connection $conn)
            {
            }

            public function getTablePrefix(): string
            {
                return $this->conn->getTablePrefix();
            }

            public function newQuery(?string $table = null): \yuandian\Database\Db\BaseQuery
            {
                return $this->conn->table($table);
            }
        };

        $this->builder = new Oracle($context);
    }

    private function buildSelect(array $overrides = []): string
    {
        $options = array_merge([
            'table'  => 'users',
            'field'  => ['*'],
            'limit'  => null,
            'offset' => null,
        ], $overrides);

        $state = new QueryState();
        $state->table = $options['table'];
        $state->field = $options['field'];
        $state->limit = $options['limit'];
        $state->offset = $options['offset'];

        return $this->builder->compileSelect($state)->statement;
    }

    public function testLimitAndOffset(): void
    {
        $sql = $this->buildSelect(['limit' => 10, 'offset' => 5]);

        $this->assertStringContainsString('OFFSET 5 ROWS', $sql);
        $this->assertStringContainsString('FETCH NEXT 10 ROWS ONLY', $sql);
        $this->assertStringContainsString('SELECT * FROM "USERS"', $sql);

        // Oracle 12c+ requires OFFSET before FETCH
        $this->assertLessThan(
            strpos($sql, 'FETCH NEXT 10 ROWS ONLY'),
            strpos($sql, 'OFFSET 5 ROWS'),
            'OFFSET must appear before FETCH in Oracle SQL'
        );
    }

    public function testLimitOnly(): void
    {
        $sql = $this->buildSelect(['limit' => 10]);

        $this->assertStringContainsString('FETCH NEXT 10 ROWS ONLY', $sql);
        $this->assertStringNotContainsString('OFFSET', $sql);
    }

    public function testOffsetOnly(): void
    {
        $sql = $this->buildSelect(['offset' => 5]);

        $this->assertStringContainsString('OFFSET 5 ROWS', $sql);
        $this->assertStringNotContainsString('FETCH', $sql);
    }

    public function testNoPagination(): void
    {
        $sql = $this->buildSelect();

        $this->assertStringNotContainsString('OFFSET', $sql);
        $this->assertStringNotContainsString('FETCH', $sql);
        $this->assertSame('SELECT * FROM "USERS"', $sql);
    }
}
