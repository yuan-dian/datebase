<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Builder;

use Closure;
use yuandian\Database\Db\BaseBuilder;
use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Db\Expression\Compiled;
use yuandian\Database\Db\Expression\Express;
use yuandian\Database\Db\Expression\Raw;
use yuandian\Database\Exceptions\DbException;
use yuandian\Database\Db\Expression\QueryState;
use yuandian\Database\Db\Expression\WhereCondition;
use yuandian\Database\Db\Expression\WhereGroup;
use yuandian\Database\Db\Expression\WhereClause;
use yuandian\Database\Db\Expression\BindContext;

class Mysql extends BaseBuilder
{
    /** 允许的 lock 字符串值（防止 SQL 注入） */
    private const LOCK_WHITELIST = [
        'FOR SHARE',
        'FOR UPDATE',
        'LOCK IN SHARE MODE',
        'FOR UPDATE NOWAIT',
        'FOR UPDATE SKIP LOCKED',
    ];

    public function compileSelect(QueryState $state): Compiled
    {
        $bind = new BindContext();

        $sql = strtr($this->selectSql, [
            '%TABLE%'    => $this->parseTable($state->table, $state->alias ?: null),
            '%DISTINCT%' => $this->parseDistinct($state->distinct),
            '%FIELD%'    => $this->parseField($state->field),
            '%JOIN%'     => $this->parseJoin($state->join),
            '%WHERE%'    => $this->parseWhere($state->where, $bind),
            '%GROUP%'    => $this->parseGroup($state->group),
            '%HAVING%'   => $this->parseHaving($state->having),
            '%ORDER%'    => $this->parseOrder($state->order),
            '%LIMIT%'    => $this->parseLimit($state->limit, $state->offset),
            '%UNION%'    => $this->parseUnion($state->union, $bind),
            '%LOCK%'     => $this->parseLock($state->lock),
            '%COMMENT%'  => $this->parseComment($state->comment),
            '%FORCE%'    => $this->parseForce($state->forceIndex),
        ]);

        return new Compiled(trim($sql), $bind->all());
    }

    public function compileInsert(string $table, array $data, ?string $comment = null): Compiled
    {
        $fields = array_keys($data);
        $values = array_values($data);
        $bind = [];
        $placeholders = [];

        foreach ($values as $val) {
            if ($val instanceof Raw) {
                $placeholders[] = $val->getValue();
            } else {
                $placeholders[] = '?';
                $bind[] = $val;
            }
        }

        $sql = strtr($this->insertSql, [
            '%TABLE%'   => $this->parseTable($table),
            '%FIELD%'   => implode(', ', array_map([$this, 'parseKey'], $fields)),
            '%DATA%'    => implode(', ', $placeholders),
            '%COMMENT%' => $this->parseComment($comment ?? ''),
        ]);

        return new Compiled($sql, $bind);
    }

    public function compileInsertAll(string $table, array $dataList, ?string $comment = null): Compiled
    {
        if (empty($dataList)) {
            return new Compiled('', []);
        }

        $fields = array_keys($dataList[0]);
        $bind = [];
        $values = [];

        foreach ($dataList as $row) {
            $placeholders = [];
            foreach ($fields as $field) {
                $val = $row[$field] ?? null;
                if ($val instanceof Raw) {
                    $placeholders[] = $val->getValue();
                } else {
                    $placeholders[] = '?';
                    $bind[] = $val;
                }
            }
            $values[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = strtr($this->insertAllSql, [
            '%TABLE%'   => $this->parseTable($table),
            '%FIELD%'   => implode(', ', array_map([$this, 'parseKey'], $fields)),
            '%DATA%'    => implode(', ', $values),
            '%COMMENT%' => $this->parseComment($comment ?? ''),
        ]);

        return new Compiled($sql, $bind);
    }

    public function compileUpdate(string $table, array $data, QueryState $state): Compiled
    {
        $setBind = [];
        $set = [];

        foreach ($data as $key => $val) {
            if ($val instanceof Raw) {
                $set[] = $this->parseKey($key) . ' = ' . $val->getValue();
            } elseif ($val instanceof Express) {
                $set[] = $this->parseKey($key) . ' = ' . $this->parseKey($key) . ' ' . $val->getValue();
            } else {
                $set[] = $this->parseKey($key) . ' = ?';
                $setBind[] = $val;
            }
        }

        $whereBind = new BindContext();
        $sql = strtr($this->updateSql, [
            '%TABLE%'   => $this->parseTable($table),
            '%SET%'     => implode(', ', $set),
            '%JOIN%'    => $this->parseJoin($state->join),
            '%WHERE%'   => $this->parseWhere($state->where, $whereBind),
            '%ORDER%'   => $this->parseOrder($state->order),
            '%LIMIT%'   => $this->parseLimit($state->limit, null),
            '%COMMENT%' => $this->parseComment($state->comment),
        ]);

        return new Compiled(trim($sql), array_merge($setBind, $whereBind->all()));
    }

    public function compileDelete(string $table, QueryState $state): Compiled
    {
        $bind = new BindContext();

        $sql = strtr($this->deleteSql, [
            '%TABLE%'   => $this->parseTable($table),
            '%USING%'   => '',
            '%JOIN%'    => $this->parseJoin($state->join),
            '%WHERE%'   => $this->parseWhere($state->where, $bind),
            '%ORDER%'   => $this->parseOrder($state->order),
            '%LIMIT%'   => $this->parseLimit($state->limit, null),
            '%COMMENT%' => $this->parseComment($state->comment),
        ]);

        return new Compiled(trim($sql), $bind->all());
    }

    protected function parseTable(string|array $table, ?string $alias = null): string
    {
        if (is_array($table)) {
            $tables = [];
            foreach ($table as $key => $val) {
                if (is_string($key)) {
                    $tables[] = $this->wrapTable($key) . ' ' . $this->parseKey($val);
                } else {
                    $tables[] = $this->wrapTable($val);
                }
            }
            return implode(',', $tables);
        }

        $sql = $this->wrapTable($table);

        if ($alias !== null && $alias !== '') {
            $sql .= ' AS ' . $this->parseKey($alias);
        }

        return $sql;
    }

    protected function parseField(array $fields): string
    {
        if ($fields === ['*']) {
            return '*';
        }

        $result = [];
        foreach ($fields as $key => $field) {
            if ($field instanceof Raw) {
                $result[] = $field->getValue();
            } elseif (is_string($key)) {
                $result[] = $this->parseKey($key) . ' AS ' . $this->parseKey($field);
            } else {
                $result[] = $this->parseKey(trim($field));
            }
        }

        return implode(', ', $result);
    }

    protected function parseKey(string $key): string
    {
        if ($key === '*') {
            return $key;
        }

        if (str_contains($key, '.')) {
            [$t, $c] = explode('.', $key, 2);
            return $this->wrap($t) . '.' . $this->wrap($c);
        }

        return $this->wrap($key);
    }

    protected function parseJoin(array $joins): string
    {
        if (empty($joins)) {
            return '';
        }

        $sql = '';
        foreach ($joins as $join) {
            $type = strtoupper((string)($join['type'] ?? 'INNER'));
            $type = in_array($type, ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'], true) ? $type : 'INNER';
            $sql .= " {$type} JOIN " . $this->parseTable($join['table']);
            $sql .= ' ON ' . $join['condition'];
        }
        return $sql;
    }

    protected function parseWhere(WhereGroup $where, BindContext $bind): string
    {
        $whereStr = $this->parseWhereGroup($where, $bind);

        return $whereStr === '' ? '' : ' WHERE ' . $whereStr;
    }

    protected function parseWhereGroup(WhereGroup $where, BindContext $bind): string
    {
        if ($where->isEmpty()) {
            return '';
        }

        $clauses = [];
        foreach (['and' => ' AND ', 'or' => ' OR '] as $prop => $connector) {
            $group = [];
            foreach ($where->{$prop} as $condition) {
                $clause = $this->parseWhereCondition($condition, $bind);
                if ($clause !== '') {
                    $group[] = $clause;
                }
            }
            if ($group !== []) {
                $clauses[] = implode($connector, $group);
            }
        }

        return implode(' OR ', $clauses);
    }

    protected function parseWhereCondition(WhereCondition $c, BindContext $bind): string
    {
        if ($c->value instanceof Raw && $c->field === '') {
            $bind->merge($c->value->getBind());
            return $c->value->getValue();
        }

        if ($c->value instanceof WhereGroup) {
            $nested = $this->parseWhereGroup($c->value, $bind);
            return $nested === '' ? '' : '( ' . $nested . ' )';
        }

        return $this->parseWhereItem($c, $bind);
    }

    protected function parseWhereItem(WhereCondition $c, BindContext $bind): string
    {
        $operator = strtoupper($c->operator);
        $key = $this->parseKey($c->field);
        $clause = new WhereClause($c->field, $key, $operator, $c->value);

        $p = $this->parser;

        return match (true) {
            in_array($operator, $p['parseLike'], true)        => $this->parseLike($clause, $bind),
            in_array($operator, $p['parseBetween'], true)     => $this->parseBetween($clause, $bind),
            in_array($operator, $p['parseIn'], true)          => $this->parseIn($clause, $bind),
            in_array($operator, $p['parseExp'], true)         => $this->parseExp($clause, $bind),
            in_array($operator, $p['parseNull'], true)        => $this->parseNull($clause, $bind),
            in_array($operator, $p['parseBetweenTime'], true) => $this->parseBetweenTime($clause, $bind),
            in_array($operator, $p['parseTime'], true)        => $this->parseTime($clause, $bind),
            in_array($operator, $p['parseExists'], true)      => $this->parseExists($clause, $bind),
            in_array($operator, $p['parseColumn'], true)      => $this->parseColumn($clause, $bind),
            default                                           => $this->parseCompare($clause, $this->operatorMap[$operator] ?? $operator, $bind),
        };
    }

    protected function parseCompare(WhereClause $c, string $operator, BindContext $bind): string
    {
        if ($c->value instanceof Raw) {
            $bind->merge($c->value->getBind());
            return $c->key . ' ' . $operator . ' ' . $c->value->getValue();
        }

        if ($c->value instanceof Closure) {
            $subQuery = $this->context->newQuery();
            $closure = $c->value;
            $closure($subQuery);
            $subState = $subQuery->getState();
            $subSql = $this->compileSelect($subState);
            $bind->merge($subSql->bind);
            return $c->key . ' ' . $operator . ' ( ' . $subSql->statement . ' )';
        }

        if ($operator === '=' && is_null($c->value)) {
            return $c->key . ' IS NULL';
        }

        $bind->add($c->value);
        return $c->key . ' ' . $operator . ' ?';
    }

    protected function parseLike(WhereClause $c, BindContext $bind): string
    {
        $bind->add($c->value);
        return $c->key . ' ' . $c->operator . ' ?';
    }

    protected function parseBetween(WhereClause $c, BindContext $bind): string
    {
        $value = $c->value;
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value) || count($value) < 2) {
            throw new DbException("BETWEEN 需要恰好 2 个元素，当前提供 " . count($value) . " 个");
        }

        $bind->add($value[0]);
        $bind->add($value[1]);
        return $c->key . ' ' . $c->operator . ' ? AND ?';
    }

    protected function parseIn(WhereClause $c, BindContext $bind): string
    {
        if (!is_array($c->value) || empty($c->value)) {
            throw new DbException("IN 条件需要非空数组，当前为空");
        }

        $placeholders = [];
        foreach ($c->value as $v) {
            if ($v instanceof Raw) {
                $placeholders[] = $v->getValue();
            } else {
                $placeholders[] = '?';
                $bind->add($v);
            }
        }

        return $c->key . ' ' . $c->operator . ' (' . implode(', ', $placeholders) . ')';
    }

    protected function parseExp(WhereClause $c, BindContext $bind): string
    {
        if ($c->value instanceof Raw) {
            return '( ' . $c->key . ' ' . $c->value->getValue() . ' )';
        }

        if (!is_string($c->value)) {
            throw new DbException("parseExp expects string or Raw, " . get_debug_type($c->value) . " given");
        }

        return '( ' . $c->key . ' ' . $c->value . ' )';
    }

    protected function parseNull(WhereClause $c, BindContext $bind): string
    {
        return $c->operator === 'NULL' ? $c->key . ' IS NULL' : $c->key . ' IS NOT NULL';
    }

    protected function parseBetweenTime(WhereClause $c, BindContext $bind): string
    {
        $value = $c->value;
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value) || count($value) < 2) {
            throw new DbException("BETWEEN TIME 需要恰好 2 个元素，当前提供 " . count($value) . " 个");
        }

        $bind->add($value[0]);
        $bind->add($value[1]);

        return $c->key . ($c->operator === 'BETWEEN TIME' ? ' BETWEEN' : ' NOT BETWEEN') . ' ? AND ?';
    }

    protected function parseTime(WhereClause $c, BindContext $bind): string
    {
        $bind->add($c->value);
        return $c->key . ' ' . substr($c->operator, 0, 2) . ' ?';
    }

    protected function parseExists(WhereClause $c, BindContext $bind): string
    {
        if ($c->value instanceof Raw) {
            $bind->merge($c->value->getBind());
            return $c->operator . ' ( ' . $c->value->getValue() . ' )';
        }

        if ($c->value instanceof Closure) {
            $subQuery = $this->context->newQuery();
            $closure = $c->value;
            $closure($subQuery);
            $subState = $subQuery->getState();
            $subSql = $this->compileSelect($subState);
            $bind->merge($subSql->bind);
            return $c->operator . ' ( ' . $subSql->statement . ' )';
        }

        return $c->operator . ' ( ' . $c->value . ' )';
    }

    protected function parseColumn(WhereClause $c, BindContext $bind): string
    {
        if (is_array($c->value) && count($c->value) === 2) {
            [$op, $compareField] = $c->value;
            $op = strtoupper($op);
            if (!in_array($op, $this->parser['parseCompare'], true)) {
                throw new DbException("无效的列比较运算符: '{$op}'，允许值: " . implode(', ', $this->parser['parseCompare']));
            }
            return '( ' . $c->key . ' ' . $op . ' ' . $this->parseKey($compareField) . ' )';
        }

        return $c->key . ' = ' . $this->parseKey($c->value);
    }

    protected function parseGroup(array $group): string
    {
        if (empty($group)) {
            return '';
        }

        $groups = array_map(fn(string $f) => $this->parseKey($f), $group);
        return ' GROUP BY ' . implode(', ', $groups);
    }

    protected function parseHaving(array $having): string
    {
        if (empty($having)) {
            return '';
        }
        return ' HAVING ' . implode(' AND ', $having);
    }

    protected function parseOrder(array $order): string
    {
        if (empty($order)) {
            return '';
        }

        $orders = [];
        foreach ($order as [$field, $dir]) {
            if ($field instanceof Raw) {
                $orders[] = $field->getValue();
            } else {
                $dir = strtoupper((string)$dir);
                if (!in_array($dir, ['ASC', 'DESC'], true)) {
                    throw new DbException("无效的排序方向: '{$dir}'，仅允许 ASC/DESC");
                }
                $orders[] = $this->parseKey($field) . ' ' . $dir;
            }
        }

        return ' ORDER BY ' . implode(', ', $orders);
    }

    protected function parseLimit(?int $limit, ?int $offset): string
    {
        $sql = '';
        if ($limit !== null) {
            if ($limit < 0) {
                throw new DbException('limit 不能为负数，当前值：' . $limit);
            }
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }
        return $sql;
    }

    protected function parseUnion(array $union, BindContext $bind): string
    {
        if (empty($union)) {
            return '';
        }

        $sql = '';
        foreach ($union as $u) {
            $type = $u['type'] ?? 'UNION';
            $type = preg_match('/^UNION( ALL)?$/i', $type) ? strtoupper($type) : null;
            if ($type === null) {
                throw new DbException("无效的 UNION 类型: '{$u['type']}'，仅允许 UNION / UNION ALL");
            }

            if ($u['query'] instanceof BaseQuery) {
                $subState = $u['query']->getState();
                $subSql = $this->compileSelect($subState);
                $sub = $subSql->statement;
                if (!empty($subState->order) || !empty($subState->limit)) {
                    $sub = 'SELECT * FROM ( ' . $sub . ' ) AS t';
                }
                $sql .= ' ' . $type . ' ' . $sub;
                $bind->merge($subSql->bind);
            } elseif (is_string($u['query'])) {
                $sub = $u['query'];
                if (preg_match('/\bORDER\s+BY\b|\bLIMIT\b/i', $sub)) {
                    $sub = 'SELECT * FROM ( ' . $sub . ' ) AS t';
                }
                $sql .= ' ' . $type . ' ' . $sub;
            }
        }

        return $sql;
    }

    protected function parseLock(bool|string $lock): string
    {
        if ($lock === false) {
            return '';
        }

        if ($lock === true) {
            return ' FOR UPDATE';
        }

        $upper = strtoupper($lock);
        foreach (self::LOCK_WHITELIST as $allowed) {
            if ($upper === $allowed) {
                return ' ' . $allowed;
            }
        }

        throw new DbException("无效的锁类型: '{$lock}'，允许值: " . implode(', ', self::LOCK_WHITELIST));
    }

    protected function parseComment(string $comment): string
    {
        if (empty($comment)) {
            return '';
        }

        if (str_contains($comment, '*/')) {
            $comment = strstr($comment, '*/', true);
        }

        return ' /* ' . $comment . ' */';
    }

    protected function parseDistinct(bool $distinct): string
    {
        return $distinct ? ' DISTINCT' : '';
    }

    protected function parseForce(string|array|false $force): string
    {
        if (empty($force)) {
            return '';
        }

        if (is_array($force)) {
            return ' FORCE INDEX (' . implode(', ', array_map([$this, 'parseKey'], $force)) . ')';
        }

        return ' FORCE INDEX (' . $this->parseKey($force) . ')';
    }

    public function wrapTable(string $table): string
    {
        return $this->wrap($this->context->getTablePrefix() . $table);
    }

    public function wrap(string|Raw $value): string
    {
        if ($value === '*') {
            return $value;
        }

        if ($value instanceof Raw) {
            return $value->getValue();
        }

        if (str_contains($value, '.')) {
            [$t, $c] = explode('.', $value, 2);
            return '`' . str_replace('`', '``', $t) . '`.`' . str_replace('`', '``', $c) . '`';
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }
}
