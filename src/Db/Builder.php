<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use Closure;
use yuandian\Database\Db\State\QueryState;
use yuandian\Database\Db\State\WhereCondition;
use yuandian\Database\Db\State\WhereGroup;

class Builder extends BaseBuilder
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
        $bind = [];

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

        return new Compiled(trim($sql), $bind);
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
        $bind = [];
        $set = [];

        foreach ($data as $key => $val) {
            if ($val instanceof Raw) {
                $set[] = $this->parseKey($key) . ' = ' . $val->getValue();
            } elseif ($val instanceof Express) {
                $set[] = $this->parseKey($key) . ' = ' . $this->parseKey($key) . ' ' . $val->getValue();
            } else {
                $set[] = $this->parseKey($key) . ' = ?';
                $bind[] = $val;
            }
        }

        $whereBind = [];
        $sql = strtr($this->updateSql, [
            '%TABLE%'   => $this->parseTable($table),
            '%SET%'     => implode(', ', $set),
            '%JOIN%'    => $this->parseJoin($state->join),
            '%WHERE%'   => $this->parseWhere($state->where, $whereBind),
            '%ORDER%'   => $this->parseOrder($state->order),
            '%LIMIT%'   => $this->parseLimit($state->limit, null),
            '%COMMENT%' => $this->parseComment($state->comment),
        ]);

        return new Compiled(trim($sql), array_merge($bind, $whereBind));
    }

    public function compileDelete(string $table, QueryState $state): Compiled
    {
        $bind = [];

        $sql = strtr($this->deleteSql, [
            '%TABLE%'   => $this->parseTable($table),
            '%USING%'   => '',
            '%JOIN%'    => $this->parseJoin($state->join),
            '%WHERE%'   => $this->parseWhere($state->where, $bind),
            '%ORDER%'   => $this->parseOrder($state->order),
            '%LIMIT%'   => $this->parseLimit($state->limit, null),
            '%COMMENT%' => $this->parseComment($state->comment),
        ]);

        return new Compiled(trim($sql), $bind);
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

        // 表别名：FROM table AS alias（仅 select 链式 alias() 提供）
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
                // 键值对：原名 => 别名
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
            // 二次防御：即使 state 被直接写入，type 也仅接受白名单值（防注入）
            $type = strtoupper((string)($join['type'] ?? 'INNER'));
            $type = in_array($type, ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'], true) ? $type : 'INNER';
            $sql .= " {$type} JOIN " . $this->parseTable($join['table']);
            $sql .= ' ON ' . $join['condition'];
        }
        return $sql;
    }

    protected function parseWhere(WhereGroup $where, array &$bind): string
    {
        $whereStr = $this->parseWhereGroup($where, $bind);

        return $whereStr === '' ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * 解析条件组，返回不带 WHERE 前缀的条件片段
     *
     * AND 组与 OR 组各自组内同逻辑连接，组间用 OR 连接（与原 $options['where'] 语义一致）
     */
    protected function parseWhereGroup(WhereGroup $where, array &$bind): string
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

    /**
     * 按 value 类型分发单条条件：
     * Raw → 原生 SQL 片段（合并自带 bind）；WhereGroup → 递归括号包裹；其余 → parseWhereItem
     */
    protected function parseWhereCondition(WhereCondition $c, array &$bind): string
    {
        // whereRaw 场景：field 为空，Raw 是整条 SQL 片段
        if ($c->value instanceof Raw && $c->field === '') {
            $bind = array_merge($bind, $c->value->getBind());
            return $c->value->getValue();
        }

        if ($c->value instanceof WhereGroup) {
            $nested = $this->parseWhereGroup($c->value, $bind);
            return $nested === '' ? '' : '( ' . $nested . ' )';
        }

        return $this->parseWhereItem($c->field, $c->operator, $c->value, $bind);
    }

    protected function parseWhereItem(string $field, string $operator, mixed $value, array &$bind): string
    {
        $operator = strtoupper($operator);
        $key = $this->parseKey($field);

        $p = $this->parser;

        return match (true) {
            in_array($operator, $p['parseLike'], true)        => $this->parseLike($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseBetween'], true)     => $this->parseBetween($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseIn'], true)          => $this->parseIn($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseExp'], true)         => $this->parseExp($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseNull'], true)        => $this->parseNull($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseBetweenTime'], true) => $this->parseBetweenTime($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseTime'], true)        => $this->parseTime($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseExists'], true)      => $this->parseExists($key, $operator, $value, $field, $bind),
            in_array($operator, $p['parseColumn'], true)      => $this->parseColumn($key, $operator, $value, $field, $bind),
            default                                      => $this->parseCompare($key, $this->operatorMap[$operator] ?? $operator, $value, $field, $bind),
        };
    }

    protected function parseCompare(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        if ($value instanceof Raw) {
            // Raw 自带 bind（如 whereRaw 生成的 Raw 值），合并避免参数错位
            $bind = array_merge($bind, $value->getBind());
            return $key . ' ' . $operator . ' ' . $value->getValue();
        }

        if ($value instanceof Closure) {
            $subQuery = $this->context->newQuery();
            $value($subQuery);
            $subState = $subQuery->getState();
            $subSql = $this->compileSelect($subState);
            $bind = array_merge($bind, $subSql->bind);
            return $key . ' ' . $operator . ' ( ' . $subSql->statement . ' )';
        }

        if ($operator === '=' && is_null($value)) {
            return $key . ' IS NULL';
        }

        $bind[] = $value;
        return $key . ' ' . $operator . ' ?';
    }

    protected function parseLike(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        $bind[] = $value;
        return $key . ' ' . $operator . ' ?';
    }

    protected function parseBetween(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if ($operator === 'NOT BETWEEN') {
            $bind[] = $value[0];
            $bind[] = $value[1];
            return $key . ' NOT BETWEEN ? AND ?';
        }

        $bind[] = $value[0];
        $bind[] = $value[1];
        return $key . ' BETWEEN ? AND ?';
    }

    protected function parseIn(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        if (empty($value)) {
            return $operator === 'IN' ? '0' : '1';
        }

        $placeholders = [];
        foreach ($value as $v) {
            if ($v instanceof Raw) {
                $placeholders[] = $v->getValue();
            } else {
                $placeholders[] = '?';
                $bind[] = $v;
            }
        }

        return $key . ' ' . $operator . ' (' . implode(', ', $placeholders) . ')';
    }

    protected function parseExp(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        if ($value instanceof Raw) {
            return '( ' . $key . ' ' . $value->getValue() . ' )';
        }
        return '( ' . $key . ' ' . $value . ' )';
    }

    protected function parseNull(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        return $operator === 'NULL' ? $key . ' IS NULL' : $key . ' IS NOT NULL';
    }

    protected function parseBetweenTime(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $bind[] = $value[0];
        $bind[] = $value[1];

        return $key . ($operator === 'BETWEEN TIME' ? ' BETWEEN' : ' NOT BETWEEN') . ' ? AND ?';
    }

    protected function parseTime(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        $bind[] = $value;
        return $key . ' ' . substr($operator, 0, 2) . ' ?';
    }

    protected function parseExists(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        if ($value instanceof Raw) {
            $bind = array_merge($bind, $value->getBind());
            return $operator . ' ( ' . $value->getValue() . ' )';
        }

        if ($value instanceof Closure) {
            $subQuery = $this->context->newQuery();
            $value($subQuery);
            $subState = $subQuery->getState();
            $subSql = $this->compileSelect($subState);
            $bind = array_merge($bind, $subSql->bind);
            return $operator . ' ( ' . $subSql->statement . ' )';
        }

        return $operator . ' ( ' . $value . ' )';
    }

    protected function parseColumn(string $key, string $operator, mixed $value, string $field, array &$bind): string
    {
        if (is_array($value) && count($value) === 2) {
            [$op, $compareField] = $value;
            $op = strtoupper($op);
            if (!in_array($op, $this->parser['parseCompare'], true)) {
                $op = '=';
            }
            return '( ' . $key . ' ' . $op . ' ' . $this->parseKey($compareField) . ' )';
        }

        return $key . ' = ' . $this->parseKey($value);
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
                // 方向白名单：仅允许 ASC/DESC，其余归 ASC，防止 SQL 注入
                $dir = strtoupper((string)$dir);
                if (!in_array($dir, ['ASC', 'DESC'], true)) {
                    $dir = 'ASC';
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
            // 负数 limit 各驱动语义不同，统一抛异常
            if ($limit < 0) {
                throw new \InvalidArgumentException('limit 不能为负数，当前值：' . $limit);
            }
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }
        return $sql;
    }

    /**
     * 生成 UNION 段
     */
    protected function parseUnion(array $union, array &$bind): string
    {
        if (empty($union)) {
            return '';
        }

        $sql = '';
        foreach ($union as $u) {
            // 二次防御：即使 state 被直接写入，type 也仅接受 UNION / UNION ALL
            $type = $u['type'] ?? 'UNION';
            $type = preg_match('/^UNION( ALL)?$/i', $type) ? strtoupper($type) : 'UNION';

            if ($u['query'] instanceof BaseQuery) {
                $subState = $u['query']->getState();
                $subSql = $this->compileSelect($subState);
                $sub = $subSql->statement;
                if (!empty($subState->order) || !empty($subState->limit)) {
                    // 子查询自带 ORDER/LIMIT 时用派生表包裹：UNION 后的 ORDER/LIMIT
                    // 会被解释为整体排序分页（SQLite/MySQL/Oracle 通用）
                    $sub = 'SELECT * FROM ( ' . $sub . ' ) AS t';
                }
                $sql .= ' ' . $type . ' ' . $sub;
                // 合并子查询的绑定参数，避免 UNION 子查询丢失 bind
                $bind = array_merge($bind, $subSql->bind);
            } elseif (is_string($u['query'])) {
                // string 分支：SQLite 不支持 UNION 分支括号（UNION (SELECT ...) 语法错），
                // 自带 ORDER/LIMIT 时与子查询分支一致用派生表限定作用域
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

        return '';
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

    public function wrap(string $value): string
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
