<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Connector;

use PDO;
use yuandian\Database\Db\PDOConnection;

class Mysql extends PDOConnection
{
    protected function parseDsn(array $config): string
    {
        $host = $config['hostname'] ?? '127.0.0.1';
        $port = $config['hostport'] ?? '3306';
        $dbname = $config['database'] ?? '';

        return "mysql:host={$host};port={$port};dbname={$dbname}";
    }

    public function getFields(string $tableName): array
    {
        $this->initConnect();
        $database = $this->getConfig('database');
        $sql = "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, EXTRA
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table
                ORDER BY ORDINAL_POSITION";

        $stmt = $this->linkId->prepare($sql);
        $stmt->execute(['database' => $database, 'table' => $tableName]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fields = [];
        foreach ($columns as $column) {
            $fields[$column['COLUMN_NAME']] = [
                'type'    => $column['DATA_TYPE'],
                'primary' => $column['COLUMN_KEY'] === 'PRI',
                'autoinc' => str_contains($column['EXTRA'] ?? '', 'auto_increment'),
            ];
        }

        return $fields;
    }

    public function getTables(string $dbName = ''): array
    {
        $this->initConnect();
        $database = $dbName ?: $this->getConfig('database');
        $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :database";

        $stmt = $this->linkId->prepare($sql);
        $stmt->execute(['database' => $database]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getQueryClass(): string
    {
        return $this->getConfig('query') ?: \yuandian\Database\Db\Query::class;
    }

    public function getBuilderClass(): string
    {
        return \yuandian\Database\Db\Builder::class;
    }

    protected function supportSavepoint(): bool
    {
        return true;
    }
}
