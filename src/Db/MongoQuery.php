<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use yuandian\Database\Db\Builder\Mongo as MongoBuilder;
use yuandian\Database\Db\Connector\Mongo as MongoConnection;

/**
 * MongoDB 查询器（Db 层）
 *
 * @extends BaseQuery<array<string, mixed>>
 */
class MongoQuery extends BaseQuery
{
    protected MongoConnection $connection;
    protected MongoBuilder $builder;

    public function __construct(MongoConnection $connection, ?string $table = null)
    {
        parent::__construct($table);

        $this->connection = $connection;
        $this->builder = $connection->getBuilder();
    }

    protected function newSubQuery(): static
    {
        return new static($this->connection, $this->options['table'] ?? null);
    }

    public function command(
        Command $command,
        string $dbName = '',
        ?ReadPreference $readPreference = null,
        $typeMap = null
    ) {
        return $this->connection->command($command, $dbName, $readPreference, $typeMap);
    }

    public function cmd($command, $extra = null, string $db = ''): array
    {
        $this->parseOptions();

        return $this->connection->cmd($this, $command, $extra, $db);
    }

    public function getDistinct(string $field): array
    {
        $result = $this->cmd('distinct', $field);

        return $result[0]['values'] ?? [];
    }

    public function listCollections(string $db = ''): array
    {
        $cursor = $this->cmd('listCollections', null, $db);
        $result = [];
        foreach ($cursor as $collection) {
            $result[] = $collection['name'];
        }

        return $result;
    }

    public function count(string $field = '*'): int
    {
        $result = $this->cmd('count');

        return $result[0]['n'] ?? 0;
    }

    public function aggregate(string $aggregate, $field, bool $force = false, bool $one = false): string|int|float|null
    {
        $result = $this->cmd('aggregate', [strtolower($aggregate), $field]);
        $value = $result[0]['aggregate'] ?? 0;

        if ($force) {
            $value += 0;
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        return null;
    }

    public function multiAggregate(array $aggregate, array $groupBy): array
    {
        $result = $this->cmd('multiAggregate', [$aggregate, $groupBy]);

        foreach ($result as $key => $row) {
            if (isset($row['_id']) && !empty($row['_id'])) {
                foreach ($row['_id'] as $k => $v) {
                    $row[$k] = $v;
                }
                unset($row['_id']);
                $result[$key] = $row;
            }
        }

        return $result;
    }

    public function inc(string $field, float|int $step = 1): static
    {
        $this->options['data'][$field] = ['$inc', $step];

        return $this;
    }

    public function dec(string $field, float|int $step = 1): static
    {
        return $this->inc($field, -1 * $step);
    }

    public function table(string $table): static
    {
        $this->options['table'] = $table;

        return $this;
    }

    public function collection(string $collection): static
    {
        return $this->table($collection);
    }

    public function typeMap($typeMap): static
    {
        $this->options['typeMap'] = $typeMap;

        return $this;
    }

    public function awaitData(bool $awaitData): static
    {
        $this->options['awaitData'] = $awaitData;

        return $this;
    }

    public function batchSize(int $batchSize): static
    {
        $this->options['batchSize'] = $batchSize;

        return $this;
    }

    public function exhaust(bool $exhaust): static
    {
        $this->options['exhaust'] = $exhaust;

        return $this;
    }

    public function modifiers(array $modifiers): static
    {
        $this->options['modifiers'] = $modifiers;

        return $this;
    }

    public function noCursorTimeout(bool $noCursorTimeout): static
    {
        $this->options['noCursorTimeout'] = $noCursorTimeout;

        return $this;
    }

    public function oplogReplay(bool $oplogReplay): static
    {
        $this->options['oplogReplay'] = $oplogReplay;

        return $this;
    }

    public function partial(bool $partial): static
    {
        $this->options['partial'] = $partial;

        return $this;
    }

    public function maxTimeMS(string $maxTimeMS): static
    {
        $this->options['maxTimeMS'] = $maxTimeMS;

        return $this;
    }

    public function collation(array $collation): static
    {
        $this->options['collation'] = $collation;

        return $this;
    }

    public function replace(bool $replace = true): static
    {
        return $this;
    }

    public function field(string|array $fields): static
    {
        if (empty($fields) || '*' == $fields) {
            return $this;
        }

        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }

        $projection = [];
        foreach ($fields as $key => $val) {
            if (is_numeric($key)) {
                $projection[$val] = 1;
            } else {
                $projection[$key] = $val;
            }
        }

        $this->options['projection'] = $projection;

        return $this;
    }

    public function withoutField(string|array $field): static
    {
        if (empty($field) || '*' == $field) {
            return $this;
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        $projection = [];
        foreach ($field as $key => $val) {
            if (is_numeric($key)) {
                $projection[$val] = 0;
            } else {
                $projection[$key] = $val;
            }
        }

        $this->options['projection'] = $projection;

        return $this;
    }

    public function skip(int $skip): static
    {
        $this->options['skip'] = $skip;

        return $this;
    }

    public function slaveOk(bool $slaveOk): static
    {
        $this->options['slaveOk'] = $slaveOk;

        return $this;
    }

    public function limit(int $limit): static
    {
        // 语义统一：与 BaseQuery::limit(int $limit) 单参一致（取前 N 条），
        // 偏移分页请用 skip()/offset()。此前双参签名 limit(offset, length)
        // 与 PDO 侧语义不一致（Mongo 下 limit(10) 会从第 10 条开始），
        // 已移除以免跨驱动代码产生静默行为差异。
        $this->options['limit'] = $limit;

        return $this;
    }

    /**
     * 排序
     *
     * @param string|array<string,int> $field 单字段名，或 [字段 => 1|-1|'asc'|'desc'] 多字段
     */
    public function order(string|array $field, string $direction = 'asc'): static
    {
        // 字段名统一走 convertFieldName（模型层 camelCase→snake_case，Db 层原样）；
        // pk_convert_id 时主键存 _id：'id' 再转 '_id'，否则 Mongo 对缺失字段排序
        // 行为怪异，skip/limit 分页会错乱（重复/丢行）
        $convert = function (string $key): string {
            $key = $this->convertFieldName($key);
            return 'id' === $key && $this->connection->getConfig('pk_convert_id') ? '_id' : $key;
        };

        if (is_array($field)) {
            $this->options['sort'] = [];
            foreach ($field as $f => $val) {
                $this->options['sort'][$convert((string)$f)] = 'asc' == strtolower((string)$val) ? 1 : -1;
            }
        } else {
            $this->options['sort'][$convert($field)] = 'asc' == strtolower($direction) ? 1 : -1;
        }

        return $this;
    }

    public function tailable(bool $tailable): static
    {
        $this->options['tailable'] = $tailable;

        return $this;
    }

    public function writeConcern(WriteConcern $writeConcern): static
    {
        $this->options['writeConcern'] = $writeConcern;

        return $this;
    }

    public function getPk(): string
    {
        return $this->connection->getConfig('pk');
    }

    public function cursor(): Cursor
    {
        return $this->getCursor();
    }

    public function getCursor(): Cursor
    {
        $this->parseOptions();

        return $this->connection->cursor($this);
    }

    public function parseOptions(): array
    {
        // 全局作用域钩子（模型层覆写实现软删过滤等）；Db 层空实现无操作。
        // 所有查询入口（find/select/update/delete/cursor/count/aggregate）必经此处，
        // 保证软删过滤与 PDO 侧 applyGlobalScopes 语义一致。
        $this->applyGlobalScopes();

        $options = $this->options;

        if (empty($options['table'])) {
            $options['table'] = $this->options['table'];
        }

        foreach (['where', 'data', 'projection', 'filter', 'json', 'with_attr', 'with_relation_attr'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = [];
            }
        }

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

        if (!isset($options['typeMap'])) {
            $options['typeMap'] = $this->connection->getConfig('type_map');
        }

        if (!isset($options['limit'])) {
            $options['limit'] = 0;
        }

        foreach (['master', 'fetch_cursor'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }

        if (isset($options['page'])) {
            [$page, $listRows] = $options['page'];

            $page = $page > 0 ? $page : 1;
            $listRows = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset = $listRows * ($page - 1);
            $options['skip'] = intval($offset);
            $options['limit'] = intval($listRows);
        }

        // chunk/offset 分页：builder 只认 skip，把 Db 层通用的 offset 映射过去
        if (isset($options['offset'])) {
            $options['skip'] = $options['offset'];
            unset($options['offset']);
        }

        $this->options = $options;

        return $options;
    }

    public function getFieldsType(): array
    {
        if (!empty($this->options['field_type'])) {
            return $this->options['field_type'];
        }

        return [];
    }

    public function getFieldType(string $field): ?string
    {
        $fieldType = $this->getFieldsType();

        return $fieldType[$field] ?? null;
    }

    public function getType(): array
    {
        return $this->getFieldsType();
    }

    public function getAutoInc(): string
    {
        return '';
    }

    public function autoInc(?string $autoInc): static
    {
        return $this;
    }

    /**
     * 返回类型取公共上界 array|object|null（PHP 协变）：Model 层子类收窄为 ?Model
     *
     * @return array<string, mixed>|object|null
     */
    public function find(): array|object|null
    {
        $this->options['limit'] = 1;
        $row = $this->connection->find($this);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function select(): array
    {
        return $this->connection->select($this);
    }

    public function insert(array $data): string
    {
        $this->options['data'] = $this->toSnakeKeys($data);

        return (string)$this->connection->insert($this, true);
    }

    public function insertAll(array $dataList): int
    {
        $dbDataList = [];
        foreach ($dataList as $data) {
            $dbDataList[] = $this->toSnakeKeys($data);
        }

        return $this->connection->insertAll($this, $dbDataList);
    }

    public function update(array $data): int
    {
        $this->options['data'] = $this->toSnakeKeys($data);

        return $this->connection->update($this);
    }

    public function delete(): int
    {
        return $this->connection->delete($this);
    }

    protected function toSnakeKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[\yuandian\Tools\utils\StrUtil::snake($key)] = $value;
        }
        return $result;
    }

    public function getConnection(): MongoConnection
    {
        return $this->connection;
    }

    public function getBuilder(): Builder|MongoBuilder
    {
        return $this->builder;
    }

    protected function applyGlobalScopes(): void
    {
    }
}
