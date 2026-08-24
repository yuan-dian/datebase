<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\helpers;

use yuandian\Database\Facade\DB;

trait RefreshDatabase
{
    protected static array $sqliteConfig = [
        'default'     => 'sqlite',
        'connections' => [
            'sqlite' => [
                'type'     => 'sqlite',
                'database' => ':memory:',
            ],
        ],
    ];

    protected function setUpSQLite(): void
    {
        DB::setConfig(static::$sqliteConfig);
    }

    protected function createTable(string $sql): void
    {
        $conn = DB::connect();
        if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?(\w+)/i', $sql, $m)) {
            $conn->execute('DROP TABLE IF EXISTS ' . $m[1]);
        }
        $conn->execute($sql);
    }

    protected function seedRows(string $table, array $columns, array $rows): void
    {
        $conn = DB::connect();
        $colStr = implode(', ', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $placeholders));

        $bind = [];
        foreach ($rows as $row) {
            array_push($bind, ...$row);
        }

        $conn->execute("INSERT INTO {$table} ({$colStr}) VALUES {$allPlaceholders}", $bind);
    }

    protected function seedTable(string $table, string $columns, string $values): void
    {
        $conn = DB::connect();
        $conn->execute("INSERT INTO {$table} ({$columns}) VALUES {$values}");
    }

    protected function queryTable(string $table, string $where = '1=1'): array
    {
        $conn = DB::connect();
        return $conn->query("SELECT * FROM {$table} WHERE {$where}");
    }
}
