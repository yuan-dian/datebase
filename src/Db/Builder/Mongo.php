<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Builder;

use MongoDB\BSON\Javascript;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Query as MongoQuery;
use yuandian\Database\Db\BuilderInterface;
use yuandian\Database\Db\Compiled;
use yuandian\Database\Db\Connector\Mongo as Connection;
use yuandian\Database\Db\MongoQuery as Query;
use yuandian\Database\Db\Raw;
use yuandian\Database\Db\State\QueryState;
use yuandian\Database\Db\State\WhereCondition;
use yuandian\Database\Db\State\WhereGroup;
use yuandian\Database\Exceptions\DbException;

class Mongo implements BuilderInterface
{
    protected Connection $connection;
    protected ObjectID|int|string|array $insertId = [];

    protected array $exp = [
        '<>'               => 'ne',
        '='                => 'eq',
        '>'                => 'gt',
        '>='               => 'gte',
        '<'                => 'lt',
        '<='               => 'lte',
        'in'               => 'in',
        'not in'           => 'nin',
        'nin'              => 'nin',
        'mod'              => 'mod',
        'exists'           => 'exists',
        'null'             => 'null',
        'notnull'          => 'not null',
        'not null'         => 'not null',
        'regex'            => 'regex',
        'type'             => 'type',
        'all'              => 'all',
        '> time'           => '> time',
        '< time'           => '< time',
        'between'          => 'between',
        'not between'      => 'not between',
        'between time'     => 'between time',
        'not between time' => 'not between time',
        'notbetween time'  => 'not between time',
        'like'             => 'like',
        'near'             => 'near',
        'size'             => 'size',
    ];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    // ---- BuilderInterface 实现：构造临时 MongoQuery 承接 state ----

    protected function queryFromState(QueryState $state): Query
    {
        $query = new Query($this->connection, $state->table ?: null);
        $query->setState($state);
        return $query;
    }

    public function compileSelect(QueryState $state): Compiled
    {
        $query = $this->queryFromState($state);
        return new Compiled($this->select($query)); // statement = MongoDB\Driver\Query
    }

    public function compileInsert(string $table, array $data, ?string $comment = null): Compiled
    {
        $query = new Query($this->connection, $table);
        $query->getState()->data = $data;
        return new Compiled($this->insert($query)); // statement = BulkWrite
    }

    public function compileInsertAll(string $table, array $dataList, ?string $comment = null): Compiled
    {
        $query = new Query($this->connection, $table);
        return new Compiled($this->insertAll($query, $dataList));
    }

    public function compileUpdate(string $table, array $data, QueryState $state): Compiled
    {
        $query = new Query($this->connection, $table);
        $query->setState($state);
        $query->getState()->data = $data;
        return new Compiled($this->update($query));
    }

    public function compileDelete(string $table, QueryState $state): Compiled
    {
        $query = new Query($this->connection, $table);
        $query->setState($state);
        return new Compiled($this->delete($query));
    }

    protected function parseKey(Query $query, string $key): string
    {
        if (str_starts_with($key, '__TABLE__.')) {
            [$collection, $key] = explode('.', $key, 2);
        }

        if ('id' == $key && $this->connection->getConfig('pk_convert_id')) {
            $key = '_id';
        }

        return trim($key);
    }

    protected function parseValue(Query $query, $value, $field = '')
    {
        if ('_id' == $field && 'ObjectID' == $this->connection->getConfig('pk_type') && is_string($value)) {
            try {
                return new ObjectID($value);
            } catch (\Throwable $e) {
                return new ObjectID();
            }
        }

        return $value;
    }

    protected function parseData(Query $query, array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $val) {
            $item = $this->parseKey($query, $key);

            if (is_object($val)) {
                $result[$item] = $val;
            } elseif (isset($val[0]) && 'exp' == $val[0]) {
                $result[$item] = $val[1];
            } else {
                $result[$item] = $this->parseValue($query, $val, $key);
            }
        }

        return $result;
    }

    protected function parseSet(Query $query, array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $val) {
            $item = $this->parseKey($query, $key);

            if (is_array($val) && isset($val[0]) && is_string($val[0]) && str_starts_with($val[0], '$')) {
                $result[$val[0]][$item] = $this->parseValue($query, $val[1], $key);
            } else {
                $result['$set'][$item] = $this->parseValue($query, $val, $key);
            }
        }

        return $result;
    }

    public function parseWhere(Query $query, WhereGroup $where): array
    {
        $filter = [];

        foreach (['and' => '$and', 'or' => '$or'] as $prop => $logic) {
            $list = [];
            foreach ($where->{$prop} as $condition) {
                [$target, $items] = $this->parseWhereCondition($query, $condition);
                if ($target === null) {
                    $list[] = $items[0];
                } else {
                    $filter[$target] = array_merge($filter[$target] ?? [], $items);
                }
            }
            if ($list !== []) {
                // 与 '|'/'&' 拆分直入的顶层组条件合并，避免覆盖（array_merge 保序）
                $filter[$logic] = array_merge($filter[$logic] ?? [], $list);
            }
        }

        return $filter;
    }

    /**
     * 单条 WhereCondition → filter 片段。
     * 返回 [目标逻辑|null, 片段列表]：null 表示并入条件自身所在组；
     * '$or'/'$and' 表示 field 含 '|'/'&' 拆分后直入顶层组（与原 parseWhere 行为一致）。
     */
    protected function parseWhereCondition(Query $query, WhereCondition $c): array
    {
        if ($c->value instanceof WhereGroup) {
            return [null, [$this->parseWhere($query, $c->value)]];
        }

        if ($c->value instanceof Raw) {
            return [null, [$this->parseWhereItem($query, $c->field, $c->value)]];
        }

        if (str_contains($c->field, '|')) {
            $items = [];
            foreach (explode('|', $c->field) as $k) {
                $items[] = $this->parseWhereItem($query, $k, [$c->operator, $c->value]);
            }
            return ['$or', $items];
        }

        if (str_contains($c->field, '&')) {
            $items = [];
            foreach (explode('&', $c->field) as $k) {
                $items[] = $this->parseWhereItem($query, $k, [$c->operator, $c->value]);
            }
            return ['$and', $items];
        }

        return [null, [$this->parseWhereItem($query, $c->field, [$c->operator, $c->value])]];
    }

    protected function parseWhereItem(Query $query, $field, $val): array
    {
        $key = $field ? $this->parseKey($query, $field) : '';

        if (!is_array($val)) {
            $val = ['=', $val];
        }
        [$exp, $value] = $val;

        if (is_array($exp)) {
            $data = [];
            foreach ($val as $value) {
                $exp = $value[0];
                $value = $value[1];
                if (!in_array($exp, $this->exp)) {
                    $exp = strtolower($exp);
                    if (isset($this->exp[$exp])) {
                        $exp = $this->exp[$exp];
                    }
                }
                $k = '$' . $exp;
                $data[$k] = $value;
            }
            $result[$key] = $data;

            return $result;
        } elseif (!in_array($exp, $this->exp)) {
            $exp = strtolower($exp);
            if (isset($this->exp[$exp])) {
                $exp = $this->exp[$exp];
            } else {
                throw new DbException('where express error:' . $exp);
            }
        }

        $result = [];
        if ('=' == $exp) {
            $result[$key] = $this->parseValue($query, $value, $key);
        } elseif (in_array($exp, ['neq', 'ne', 'gt', 'egt', 'gte', 'lt', 'lte', 'elt', 'mod'])) {
            $k = '$' . $exp;
            $result[$key] = [$k => $this->parseValue($query, $value, $key)];
        } elseif ('null' == $exp) {
            $result[$key] = null;
        } elseif ('not null' == $exp) {
            $result[$key] = ['$ne' => null];
        } elseif ('all' == $exp) {
            $result[$key] = ['$all', $this->parseValue($query, $value, $key)];
        } elseif ('between' == $exp) {
            $value = is_array($value) ? $value : explode(',', $value);
            $result[$key] = [
                '$gte' => $this->parseValue($query, $value[0], $key),
                '$lte' => $this->parseValue($query, $value[1], $key)
            ];
        } elseif ('not between' == $exp) {
            $value = is_array($value) ? $value : explode(',', $value);
            $result[$key] = [
                '$lt' => $this->parseValue($query, $value[0], $key),
                '$gt' => $this->parseValue($query, $value[1], $key)
            ];
        } elseif ('exists' == $exp) {
            $result[$key] = ['$exists' => (bool)$value];
        } elseif ('type' == $exp) {
            $result[$key] = ['$type' => intval($value)];
        } elseif ('exp' == $exp) {
            $result['$where'] = $value instanceof Javascript ? $value : new Javascript($value);
        } elseif ('like' == $exp) {
            if ($value instanceof Regex) {
                $result[$key] = $value;
            } else {
                $value = preg_quote($value, '/');
                $result[$key] = new Regex($value, 'i');
            }
        } elseif (in_array($exp, ['nin', 'in'])) {
            $value = is_array($value) ? $value : explode(',', $value);
            foreach ($value as $k => $val) {
                $value[$k] = $this->parseValue($query, $val, $key);
            }
            $result[$key] = ['$' . $exp => $value];
        } elseif ('regex' == $exp) {
            $result[$key] = $value instanceof Regex ? $value : new Regex($value, 'i');
        } elseif ('< time' == $exp) {
            $result[$key] = ['$lt' => $this->parseDateTime($query, $value, $field)];
        } elseif ('> time' == $exp) {
            $result[$key] = ['$gt' => $this->parseDateTime($query, $value, $field)];
        } elseif ('between time' == $exp) {
            $value = is_array($value) ? $value : explode(',', $value);
            $result[$key] = [
                '$gte' => $this->parseDateTime($query, $value[0], $field),
                '$lte' => $this->parseDateTime($query, $value[1], $field)
            ];
        } elseif ('not between time' == $exp) {
            $value = is_array($value) ? $value : explode(',', $value);
            $result[$key] = [
                '$lt' => $this->parseDateTime($query, $value[0], $field),
                '$gt' => $this->parseDateTime($query, $value[1], $field)
            ];
        } elseif ('near' == $exp) {
            $result[$key] = ['$near' => $this->parseValue($query, $value, $key)];
        } elseif ('size' == $exp) {
            $result[$key] = ['$size' => intval($value)];
        } else {
            $result[$key] = $this->parseValue($query, $value, $key);
        }

        return $result;
    }

    protected function parseDateTime(Query $query, $value, $key)
    {
        $type = $query->getFieldType($key);

        if ($type) {
            if (is_string($value)) {
                $value = strtotime($value) ?: $value;
            }

            if (is_int($value)) {
                if (preg_match('/(datetime|timestamp)/is', $type)) {
                    $value = date('Y-m-d H:i:s', $value);
                } elseif (preg_match('/(date)/is', $type)) {
                    $value = date('Y-m-d', $value);
                }
            }
        }

        return $value;
    }

    public function getLastInsID()
    {
        return $this->insertId;
    }

    public function insert(Query $query): BulkWrite
    {
        $state = $query->getState();

        $data = $this->parseData($query, $state->data);

        $bulk = new BulkWrite();

        if ($insertId = $bulk->insert($data)) {
            $this->insertId = $insertId;
        }

        $this->log('insert', $data, $state->data);

        return $bulk;
    }

    public function insertAll(Query $query, array $dataSet): BulkWrite
    {
        $bulk = new BulkWrite();
        $state = $query->getState();

        $this->insertId = [];
        foreach ($dataSet as $data) {
            $data = $this->parseData($query, $data);
            if ($insertId = $bulk->insert($data)) {
                $this->insertId[] = $insertId;
            }
        }

        $this->log('insert', $dataSet, $state->data);

        return $bulk;
    }

    public function update(Query $query): BulkWrite
    {
        $state = $query->getState();

        $data = $this->parseSet($query, $state->data);
        $where = $this->parseWhere($query, $state->where);

        if (1 == $state->limit) {
            $updateOptions = ['multi' => false];
        } else {
            $updateOptions = ['multi' => true];
        }

        $bulk = new BulkWrite();

        $bulk->update($where, $data, $updateOptions);

        $this->log('update', $data, $where);

        return $bulk;
    }

    public function delete(Query $query): BulkWrite
    {
        $state = $query->getState();
        $where = $this->parseWhere($query, $state->where);

        $bulk = new BulkWrite();

        if (1 == $state->limit) {
            $deleteOptions = ['limit' => 1];
        } else {
            $deleteOptions = ['limit' => 0];
        }

        $bulk->delete($where, $deleteOptions);

        $this->log('remove', $where, $deleteOptions);

        return $bulk;
    }

    public function select(Query $query, bool $one = false): MongoQuery
    {
        $state = $query->getState();

        $where = $this->parseWhere($query, $state->where);

        // 从 state 组装 MongoDB\Driver\Query 选项（与 parseOptions 的驱动相关键一致）
        $options = [];
        if ($state->limit !== null) {
            $options['limit'] = $state->limit;
        }

        if ($state->field !== ['*']) {
            $options['projection'] = $state->field;
        }

        if (!empty($state->order)) {
            $options['sort'] = $state->order;
        }

        if ($state->comment !== '') {
            $options['comment'] = $state->comment;
        }

        if ($state->offset !== null) {
            $options['skip'] = $state->offset;
        }

        foreach ($state->extra as $name => $value) {
            $options[$name] = $value;
        }

        // comment/maxTimeMS 合并进 modifiers（与 parseOptions 语义一致）
        $modifiers = empty($options['modifiers']) ? [] : $options['modifiers'];
        if (isset($options['comment'])) {
            $modifiers['$comment'] = $options['comment'];
        }
        if (isset($options['maxTimeMS'])) {
            $modifiers['$maxTimeMS'] = $options['maxTimeMS'];
        }
        if (!empty($modifiers)) {
            $options['modifiers'] = $modifiers;
        }

        if ($one) {
            $options['limit'] = 1;
        }

        $query = new MongoQuery($where, $options);

        $this->log('find', $where, $options);

        return $query;
    }

    public function count(Query $query): Command
    {
        $state = $query->getState();

        $cmd['count'] = $state->table;
        $cmd['query'] = (object)$this->parseWhere($query, $state->where);

        foreach (['hint', 'limit', 'maxTimeMS', 'skip'] as $option) {
            if (isset($state->extra[$option])) {
                $cmd[$option] = $state->extra[$option];
            }
        }

        if ($state->limit !== null) {
            $cmd['limit'] = $state->limit;
        }

        if ($state->offset !== null) {
            $cmd['skip'] = $state->offset;
        }

        $command = new Command($cmd);
        $this->log('cmd', 'count', $cmd);

        return $command;
    }

    public function aggregate(Query $query, array $extra): Command
    {
        $state = $query->getState();
        [$fun, $field] = $extra;

        if ('id' == $field && $this->connection->getConfig('pk_convert_id')) {
            $field = '_id';
        }

        $group = isset($state->extra['group']) ? '$' . $state->extra['group'] : null;

        $pipeline = [
            ['$match' => (object)$this->parseWhere($query, $state->where)],
            ['$group' => ['_id' => $group, 'aggregate' => ['$' . $fun => '$' . $field]]],
        ];

        $cmd = [
            'aggregate'    => $state->table,
            'allowDiskUse' => true,
            'pipeline'     => $pipeline,
            'cursor'       => new \stdClass(),
        ];

        foreach (['explain', 'collation', 'bypassDocumentValidation', 'readConcern'] as $option) {
            if (isset($state->extra[$option])) {
                $cmd[$option] = $state->extra[$option];
            }
        }

        $command = new Command($cmd);

        $this->log('aggregate', $cmd);

        return $command;
    }

    public function multiAggregate(Query $query, $extra): Command
    {
        $state = $query->getState();

        [$aggregate, $groupBy] = $extra;

        $groups = ['_id' => []];

        foreach ($groupBy as $field) {
            $groups['_id'][$field] = '$' . $field;
        }

        foreach ($aggregate as $fun => $field) {
            $groups[$field . '_' . $fun] = ['$' . $fun => '$' . $field];
        }

        $pipeline = [
            ['$match' => (object)$this->parseWhere($query, $state->where)],
            ['$group' => $groups],
        ];

        $cmd = [
            'aggregate'    => $state->table,
            'allowDiskUse' => true,
            'pipeline'     => $pipeline,
            'cursor'       => new \stdClass(),
        ];

        foreach (['explain', 'collation', 'bypassDocumentValidation', 'readConcern'] as $option) {
            if (isset($state->extra[$option])) {
                $cmd[$option] = $state->extra[$option];
            }
        }

        $command = new Command($cmd);
        $this->log('group', $cmd);

        return $command;
    }

    public function distinct(Query $query, $field): Command
    {
        $state = $query->getState();

        $cmd = [
            'distinct' => $state->table,
            'key'      => $field,
        ];

        // 原 !empty($options['where']) 对对象恒真，等价于无条件设置 query
        $cmd['query'] = (object)$this->parseWhere($query, $state->where);

        if (isset($state->extra['maxTimeMS'])) {
            $cmd['maxTimeMS'] = $state->extra['maxTimeMS'];
        }

        $command = new Command($cmd);

        $this->log('cmd', 'distinct', $cmd);

        return $command;
    }

    public function listcollections(): Command
    {
        $cmd = ['listCollections' => 1];
        $command = new Command($cmd);

        $this->log('cmd', 'listCollections', $cmd);

        return $command;
    }

    public function collStats(Query $query): Command
    {
        $state = $query->getState();

        $cmd = ['collStats' => $state->table];
        $command = new Command($cmd);

        $this->log('cmd', 'collStats', $cmd);

        return $command;
    }

    protected function log($type, $data, $options = [])
    {
        $this->connection->mongoLog($type, $data, $options);
    }
}
