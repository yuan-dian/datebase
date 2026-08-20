<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Connector;

use PDO;
use yuandian\Database\Db\Builder\Oracle as OracleBuilder;
use yuandian\Database\Db\PDOConnection;

class Oracle extends PDOConnection
{
    protected function parseDsn(array $config): string
    {
        $host = $config['hostname'] ?? '127.0.0.1';
        $port = $config['hostport'] ?? '1521';
        $dbname = $config['database'] ?? 'xe';

        return "oci:dbname={$host}:{$port}/{$dbname}";
    }

    public function getFields(string $tableName): array
    {
        $this->initConnect();
        $tableName = strtoupper($tableName);
        $sql = "SELECT COLUMN_NAME, DATA_TYPE, NULLABLE
                FROM ALL_TAB_COLUMNS
                WHERE TABLE_NAME = :table
                ORDER BY COLUMN_ID";

        $stmt = $this->linkID->prepare($sql);
        $stmt->execute(['table' => $tableName]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pkSql = "SELECT COLUMN_NAME FROM ALL_CONSTRAINTS c
                  JOIN ALL_CONS_COLUMNS cc ON c.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
                  WHERE c.TABLE_NAME = :table AND c.CONSTRAINT_TYPE = 'P'";
        $pkStmt = $this->linkID->prepare($pkSql);
        $pkStmt->execute(['table' => $tableName]);
        $pkColumns = $pkStmt->fetchAll(PDO::FETCH_COLUMN);

        $fields = [];
        foreach ($columns as $column) {
            // 列名转大写与 Oracle::wrap 大写输出对齐（结果集行键为大写）
            $fields[strtoupper($column['COLUMN_NAME'])] = [
                'type'    => strtolower($column['DATA_TYPE']),
                'primary' => in_array($column['COLUMN_NAME'], $pkColumns),
                'autoinc' => false,
            ];
        }

        return $fields;
    }

    public function getTables(string $dbName = ''): array
    {
        $this->initConnect();
        $sql = "SELECT TABLE_NAME FROM USER_TABLES ORDER BY TABLE_NAME";
        $stmt = $this->linkID->query($sql);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strtolower', $tables);
    }

    public function getQueryClass(): string
    {
        return $this->getConfig('query') ?: \yuandian\Database\Db\Query::class;
    }

    public function getBuilderClass(): string
    {
        return $this->getConfig('builder') ?: OracleBuilder::class;
    }
}
