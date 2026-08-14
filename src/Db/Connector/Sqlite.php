<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Connector;

use PDO;
use yuandian\Database\Db\Builder\Sqlite as SqliteBuilder;
use yuandian\Database\Db\PDOConnection;

class Sqlite extends PDOConnection
{
    protected function parseDsn(array $config): string
    {
        return 'sqlite:' . ($config['database'] ?? ':memory:');
    }

    public function getFields(string $tableName): array
    {
        $sql = "PRAGMA table_info({$tableName})";
        $stmt = $this->linkID->query($sql);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fields = [];
        foreach ($columns as $column) {
            $fields[$column['name']] = [
                'type'    => $column['type'],
                'primary' => (bool)$column['pk'],
                'autoinc' => (bool)$column['pk'] && str_contains(strtoupper($column['type']), 'INTEGER'),
            ];
        }

        return $fields;
    }

    public function getTables(string $dbName = ''): array
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
        $stmt = $this->linkID->query($sql);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getQueryClass(): string
    {
        return $this->getConfig('query') ?: \yuandian\Database\Db\Query::class;
    }

    public function getBuilderClass(): string
    {
        return $this->getConfig('builder') ?: SqliteBuilder::class;
    }
}
