<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use Closure;

class Builder extends BaseBuilder
{

    public function select(array $options): array
    {
        $bind = [];

        $sql = str_replace(
            [
                '%TABLE%',
                '%DISTINCT%',
                '%EXTRA%',
                '%FIELD%',
                '%JOIN%',
                '%WHERE%',
                '%GROUP%',
                '%HAVING%',
                '%ORDER%',
                '%LIMIT%',
                '%UNION%',
                '%LOCK%',
                '%COMMENT%',
                '%FORCE%'
            ],
            [
                $this->parseTable($options['table']),
                $this->parseDistinct($options['distinct'] ?? false),
                $this->parseExtra($options['extra'] ?? ''),
                $this->parseField($options['field'] ?? ['*']),
                $this->parseJoin($options['join'] ?? []),
                $this->parseWhere($options['where'] ?? [], $bind),
                $this->parseGroup($options['group'] ?? []),
                $this->parseHaving($options['having'] ?? []),
                $this->parseOrder($options['order'] ?? []),
                $this->parseLimit($options['limit'] ?? null, $options['offset'] ?? null),
                $this->parseUnion($options['union'] ?? []),
                $this->parseLock($options['lock'] ?? false),
                $this->parseComment($options['comment'] ?? ''),
                $this->parseForce($options['force'] ?? ''),
            ],
            $this->selectSql
        );

        return [trim($sql), $bind];
    }

    public function insert(string $table, array $data): array
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

        $sql = str_replace(
            ['%EXTRA%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                '',
                $this->parseTable($table),
                implode(', ', array_map([$this, 'parseKey'], $fields)),
                implode(', ', $placeholders),
                '',
            ],
            $this->insertSql
        );

        return [$sql, $bind];
    }

    public function insertAll(string $table, array $dataList): array
    {
        if (empty($dataList)) {
            return ['', []];
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

        $sql = str_replace(
            ['%EXTRA%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                '',
                $this->parseTable($table),
                implode(', ', array_map([$this, 'parseKey'], $fields)),
                implode(', ', $values),
                '',
            ],
            $this->insertAllSql
        );

        return [$sql, $bind];
    }

    public function selectInsert(BaseQuery $query, array $fields, string $table): array
    {
        $sourceSql = $this->select($query->getOptions());

        $sql = str_replace(
            ['%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $this->parseTable($table),
                implode(', ', array_map([$this, 'parseKey'], $fields)),
                $sourceSql[0],
                '',
            ],
            'INSERT INTO %TABLE% (%FIELD%) %DATA% %COMMENT%'
        );

        return [trim($sql), $sourceSql[1]];
    }

    public function insertAllByKeys(string $table, array $keys, array $values): array
    {
        if (empty($values)) {
            return ['', []];
        }

        $bind = [];
        $rows = [];

        foreach ($values as $row) {
            $placeholders = [];
            foreach ($keys as $key) {
                $val = $row[$key] ?? null;
                if ($val instanceof Raw) {
                    $placeholders[] = $val->getValue();
                } else {
                    $placeholders[] = '?';
                    $bind[] = $val;
                }
            }
            $rows[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = str_replace(
            ['%EXTRA%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                '',
                $this->parseTable($table),
                implode(', ', array_map([$this, 'parseKey'], $keys)),
                implode(', ', $rows),
                '',
            ],
            $this->insertAllSql
        );

        return [$sql, $bind];
    }

    public function update(string $table, array $data, array $where, array $options = []): array
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
        $sql = str_replace(
            ['%TABLE%', '%EXTRA%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($table),
                $this->parseExtra($options['extra'] ?? ''),
                implode(', ', $set),
                $this->parseJoin($options['join'] ?? []),
                $this->parseWhere($where, $whereBind),
                $this->parseOrder($options['order'] ?? []),
                $this->parseLimit($options['limit'] ?? null, null),
                $this->parseLock($options['lock'] ?? false),
                $this->parseComment($options['comment'] ?? ''),
            ],
            $this->updateSql
        );

        return [trim($sql), array_merge($bind, $whereBind)];
    }

    public function delete(string $table, array $where, array $options = []): array
    {
        $bind = [];

        $sql = str_replace(
            ['%TABLE%', '%EXTRA%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($table),
                $this->parseExtra($options['extra'] ?? ''),
                '',
                $this->parseJoin($options['join'] ?? []),
                $this->parseWhere($where, $bind),
                $this->parseOrder($options['order'] ?? []),
                $this->parseLimit($options['limit'] ?? null, null),
                $this->parseLock($options['lock'] ?? false),
                $this->parseComment($options['comment'] ?? ''),
            ],
            $this->deleteSql
        );

        return [trim($sql), $bind];
    }

    protected function parseTable(string|array $table): string
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

        return $this->wrapTable($table);
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
                $trimmed = trim($field);
                if (stripos($trimmed, ' AS ') !== false) {
                    [$original, $alias] = preg_split('/\s+AS\s+/i', $trimmed, 2);
                    $result[] = $this->parseKey($original) . ' AS ' . $this->parseKey($alias);
                } else {
                    $result[] = $this->parseKey($trimmed);
                }
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
            $sql .= " {$join['type']} JOIN " . $this->parseTable($join['table']);
            $sql .= ' ON ' . $join['condition'];
        }
        return $sql;
    }

    protected function parseWhere(array $where, array &$bind): string
    {
        $whereStr = $this->parseWhereGroup($where, $bind);

        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * 解析条件组，返回不带 WHERE 前缀的条件片段
     *
     * 嵌套闭包（子查询条件）递归调用本方法并包裹括号
     */
    protected function parseWhereGroup(array $where, array &$bind): string
    {
        if (empty($where)) {
            return '';
        }

        $whereStr = '';

        foreach ($where as $logic => $conditions) {
            if (!is_array($conditions)) {
                continue;
            }

            foreach ($conditions as $condition) {
                // Handle Raw expressions
                if ($condition instanceof Raw) {
                    $clause = $condition->getValue();
                    if (!empty($condition->getBind())) {
                        $bind = array_merge($bind, $condition->getBind());
                    }
                } // Handle Closure (nested conditions)
                elseif ($condition instanceof \Closure) {
                    $subBind = [];
                    $subWhere = $condition();
                    $nested = $this->parseWhereGroup($subWhere, $subBind);
                    $clause = empty($nested) ? '' : '( ' . $nested . ' )';
                    $bind = array_merge($bind, $subBind);
                } // Handle array conditions [field, operator, value]
                elseif (is_array($condition) && count($condition) >= 2) {
                    $field = $condition[0];
                    $operator = strtoupper($condition[1]);
                    $value = $condition[2] ?? null;

                    $clause = $this->parseWhereItem($field, $operator, $value, $bind);
                } else {
                    continue;
                }

                if (empty($clause)) {
                    continue;
                }

                $connector = $logic === 'OR' ? ' OR ' : ' AND ';
                if (empty($whereStr)) {
                    $whereStr = $clause;
                } else {
                    $whereStr .= $connector . $clause;
                }
            }
        }

        return $whereStr;
    }

    protected function parseWhereItem(string $field, string $exp, mixed $value, array &$bind): string
    {
        $exp = strtoupper($exp);
        $key = $this->parseKey($field);

        foreach ($this->parser as $method => $operators) {
            if (in_array($exp, $operators)) {
                return $this->$method($key, $exp, $value, $field, $bind);
            }
        }

        if (isset($this->exp[$exp])) {
            $exp = $this->exp[$exp];
        }

        return $this->parseCompare($key, $exp, $value, $field, $bind);
    }

    protected function parseCompare(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        if ($value instanceof Raw) {
            return $key . ' ' . $exp . ' ' . $value->getValue();
        }

        if ($value instanceof Closure) {
            $subQuery = new Query($this->connection, '');
            $value($subQuery);
            $subSql = $this->select($subQuery->getOptions());
            return $key . ' ' . $exp . ' ' . $subSql[0];
        }

        if ($exp === '=' && is_null($value)) {
            return $key . ' IS NULL';
        }

        $bind[] = $value;
        return $key . ' ' . $exp . ' ?';
    }

    protected function parseLike(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        $bind[] = $value;
        return $key . ' ' . $exp . ' ?';
    }

    protected function parseBetween(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if ($exp === 'NOT BETWEEN') {
            $bind[] = $value[0];
            $bind[] = $value[1];
            return $key . ' NOT BETWEEN ? AND ?';
        }

        $bind[] = $value[0];
        $bind[] = $value[1];
        return $key . ' BETWEEN ? AND ?';
    }

    protected function parseIn(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        if (empty($value)) {
            return $exp === 'IN' ? '0' : '1';
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

        return $key . ' ' . $exp . ' (' . implode(', ', $placeholders) . ')';
    }

    protected function parseExp(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        if ($value instanceof Raw) {
            return '( ' . $key . ' ' . $value->getValue() . ' )';
        }
        return '( ' . $key . ' ' . $value . ' )';
    }

    protected function parseNull(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        return $exp === 'NULL' ? $key . ' IS NULL' : $key . ' IS NOT NULL';
    }

    protected function parseBetweenTime(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $bind[] = $value[0];
        $bind[] = $value[1];

        return $key . ($exp === 'BETWEEN TIME' ? ' BETWEEN' : ' NOT BETWEEN') . ' ? AND ?';
    }

    protected function parseTime(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        $bind[] = $value;
        return $key . ' ' . substr($exp, 0, 2) . ' ?';
    }

    protected function parseExists(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        if ($value instanceof Raw) {
            return $exp . ' ( ' . $value->getValue() . ' )';
        }

        if ($value instanceof Closure) {
            $subQuery = new Query($this->connection, '');
            $value($subQuery);
            $subSql = $this->select($subQuery->getOptions());
            return $exp . ' ( ' . $subSql[0] . ' )';
        }

        return $exp . ' ( ' . $value . ' )';
    }

    protected function parseColumn(string $key, string $exp, mixed $value, string $field, array &$bind): string
    {
        if (is_array($value) && count($value) === 2) {
            [$op, $compareField] = $value;
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
                $orders[] = $this->parseKey($field) . ' ' . strtoupper($dir);
            }
        }

        return ' ORDER BY ' . implode(', ', $orders);
    }

    protected function parseLimit(?int $limit, ?int $offset): string
    {
        $sql = '';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }
        return $sql;
    }

    protected function parseUnion(array $union): string
    {
        if (empty($union)) {
            return '';
        }

        $sql = '';
        foreach ($union as $u) {
            $type = $u['type'] ?? 'UNION';
            if ($u['query'] instanceof BaseQuery) {
                $subSql = $this->select($u['query']->getOptions());
                $sql .= ' ' . $type . ' ' . $subSql[0];
            } elseif (is_string($u['query'])) {
                $sql .= ' ' . $type . ' ( ' . $u['query'] . ' )';
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

        return ' ' . $lock;
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

    protected function parseExtra(string $extra): string
    {
        return $extra ? ' ' . $extra : '';
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
        $prefix = $this->connection->getTablePrefix();
        return $this->wrap($prefix . $table);
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
