<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\Db\State\QueryState;
use yuandian\Database\Db\State\WhereGroup;

abstract class BaseBuilder implements BuilderInterface
{
    protected QueryContext $context;

    protected array $exp = [
        'NOTLIKE'         => 'NOT LIKE',
        'NOTIN'           => 'NOT IN',
        'NOTBETWEEN'      => 'NOT BETWEEN',
        'NOTEXISTS'       => 'NOT EXISTS',
        'NOTNULL'         => 'NOT NULL',
        'NOTBETWEEN TIME' => 'NOT BETWEEN TIME',
    ];

    protected array $parser = [
        'parseCompare'     => ['=', '<>', '>', '>=', '<', '<='],
        'parseLike'        => ['LIKE', 'NOT LIKE'],
        'parseBetween'     => ['NOT BETWEEN', 'BETWEEN'],
        'parseIn'          => ['NOT IN', 'IN'],
        'parseExp'         => ['EXP'],
        'parseNull'        => ['NOT NULL', 'NULL'],
        'parseBetweenTime' => ['BETWEEN TIME', 'NOT BETWEEN TIME'],
        'parseTime'        => ['< TIME', '> TIME', '<= TIME', '>= TIME'],
        'parseExists'      => ['NOT EXISTS', 'EXISTS'],
        'parseColumn'      => ['COLUMN'],
    ];

    protected string $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%UNION%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    protected string $insertSql = 'INSERT INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';

    protected string $insertAllSql = 'INSERT INTO %TABLE% (%FIELD%) VALUES %DATA% %COMMENT%';

    protected string $updateSql = 'UPDATE %TABLE% SET %SET%%JOIN%%WHERE%%ORDER%%LIMIT% %COMMENT%';

    protected string $deleteSql = 'DELETE FROM %TABLE%%USING%%JOIN%%WHERE%%ORDER%%LIMIT% %COMMENT%';

    public function __construct(QueryContext $context)
    {
        $this->context = $context;
    }

    abstract public function compileSelect(QueryState $state): Compiled;
    abstract public function compileInsert(string $table, array $data, ?string $comment = null): Compiled;
    abstract public function compileInsertAll(string $table, array $dataList, ?string $comment = null): Compiled;
    abstract public function compileUpdate(string $table, array $data, QueryState $state): Compiled;
    abstract public function compileDelete(string $table, QueryState $state): Compiled;

    abstract protected function parseTable(string|array $table, ?string $alias = null): string;
    abstract protected function parseField(array $fields): string;
    abstract protected function parseKey(string $key): string;
    abstract protected function parseJoin(array $joins): string;
    abstract protected function parseWhere(WhereGroup $where, array &$bind): string;
    abstract protected function parseGroup(array $group): string;
    abstract protected function parseHaving(array $having): string;
    abstract protected function parseOrder(array $order): string;
    abstract protected function parseLimit(?int $limit, ?int $offset): string;
    abstract protected function parseUnion(array $union, array &$bind): string;
    abstract protected function parseLock(bool|string $lock): string;
    abstract protected function parseComment(string $comment): string;
    abstract protected function parseDistinct(bool $distinct): string;
    abstract protected function parseForce(string|array|false $force): string;

    abstract public function wrapTable(string $table): string;
    abstract public function wrap(string $value): string;
}