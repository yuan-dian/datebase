<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use yuandian\Database\Db\Builder\Mongo as MongoBuilder;
use yuandian\Database\Db\Connector\Mongo as MongoConnection;
use yuandian\Database\Model\Model;

class MongoQuery extends BaseQuery
{
    protected MongoConnection $connection;
    protected MongoBuilder $builder;

    public function __construct(MongoConnection $connection, string $modelClass)
    {
        parent::__construct($modelClass);

        $this->connection = $connection;
        $this->builder = $connection->getBuilder();
    }

    protected function newSubQuery(): static
    {
        return new static($this->connection, $this->modelClass);
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

    public function aggregate(string $aggregate, $field, bool $force = false, bool $one = false): mixed
    {
        $result = $this->cmd('aggregate', [strtolower($aggregate), $field]);
        $value = $result[0]['aggregate'] ?? 0;

        if ($force) {
            $value += 0;
        }

        return $value;
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

    public function limit(int $offset, ?int $length = null): static
    {
        if (is_null($length)) {
            $length = $offset;
            $offset = 0;
        }

        $this->options['skip'] = $offset;
        $this->options['limit'] = $length;

        return $this;
    }

    public function order(string $field, string $direction = 'asc'): static
    {
        if (is_array($field)) {
            $this->options['sort'] = array_map(function ($val) {
                return 'asc' == strtolower($val) ? 1 : -1;
            }, $field);
        } else {
            $this->options['sort'][$field] = 'asc' == strtolower($direction) ? 1 : -1;
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
        return $this->pk ?: $this->connection->getConfig('pk');
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
        $options = $this->options;

        if (empty($options['table'])) {
            $options['table'] = $this->getTable();
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
            $options['typeMap'] = $this->getConfig('type_map');
        }

        if (!isset($options['limit'])) {
            $options['limit'] = 0;
        }

        foreach (['master', 'fetch_sql', 'fetch_cursor'] as $name) {
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

    public function find(): Model|string|null
    {
        $this->options['limit'] = 1;
        $filter = $this->builder->buildFilter($this->options);
        $sort = $this->builder->buildSort($this->options);
        $projection = $this->builder->buildProjection($this->options);

        $driverOpts = $this->buildDriverOptions($sort, $projection, 1, null);

        $rows = $this->connection->find($this->options['table'], $filter, $driverOpts);

        if (empty($rows)) {
            return null;
        }

        $model = $this->toModel($rows[0]);

        if (!empty($this->options['with'])) {
            $model->load(...$this->options['with']);
        }

        return $model;
    }

    public function select(): array|string
    {
        $filter = $this->builder->buildFilter($this->options);
        $sort = $this->builder->buildSort($this->options);
        $projection = $this->builder->buildProjection($this->options);

        $driverOpts = $this->buildDriverOptions(
            $sort,
            $projection,
            $this->options['limit'],
            $this->options['offset']
        );

        $rows = $this->connection->find($this->options['table'], $filter, $driverOpts);
        $models = [];

        foreach ($rows as $row) {
            $models[] = $this->toModel($row);
        }

        return $models;
    }

    public function insert(array $data): string
    {
        $dbData = $this->toSnakeKeys($data);

        return $this->connection->insertOne($this->options['table'], $dbData);
    }

    public function insertAll(array $dataList): int
    {
        $dbDataList = [];
        foreach ($dataList as $data) {
            $dbDataList[] = $this->toSnakeKeys($data);
        }

        return $this->connection->insertMany($this->options['table'], $dbDataList);
    }

    public function update(array $data): int
    {
        $filter = $this->builder->buildFilter($this->options);
        $dbData = $this->toSnakeKeys($data);

        return $this->connection->updateMany($this->options['table'], $filter, $dbData);
    }

    public function delete(): int
    {
        $filter = $this->builder->buildFilter($this->options);

        return $this->connection->deleteMany($this->options['table'], $filter);
    }

    protected function toModel(array $row): Model
    {
        foreach ($row as $column => $value) {
            if ($value instanceof \MongoDB\BSON\ObjectID) {
                $row[$column] = (string)$value;
            }
        }

        return parent::toModel($row);
    }

    protected function buildDriverOptions(
        array $sort,
        ?array $projection,
        ?int $limit,
        ?int $offset
    ): array {
        $opts = [];

        if (!empty($sort)) {
            $opts['sort'] = $sort;
        }
        if ($projection !== null) {
            $opts['projection'] = $projection;
        }
        if ($limit !== null) {
            $opts['limit'] = $limit;
        }
        if ($offset !== null) {
            $opts['skip'] = $offset;
        }

        return $opts;
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
