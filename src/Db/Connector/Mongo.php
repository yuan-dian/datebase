<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Connector;

use Closure;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Manager;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use PDO;
use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Db\BuilderInterface;
use yuandian\Database\Db\Builder\Mongo as MongoBuilder;
use yuandian\Database\Db\Connection;
use yuandian\Database\Db\MongoQuery as Query;
use yuandian\Database\Exceptions\DbException;
use yuandian\Database\Model\MongoModelQuery;

class Mongo extends Connection
{
    protected string $dbName = '';
    protected array|string $typeMap = 'array';
    protected ?Manager $mongo = null;
    protected ?Cursor $cursor = null;
    protected ?string $sessionUuid = null;
    protected array $sessions = [];

    /** @var array<int, Manager> */
    protected array $links = [];
    protected ?Manager $linkRead = null;
    protected ?Manager $linkWrite = null;
    protected string $queryStr = '';

    protected array $config = [
        'type'            => '',
        'hostname'        => '',
        'database'        => '',
        'is_replica_set'  => false,
        'username'        => '',
        'password'        => '',
        'auth_source'     => '',
        'hostport'        => '',
        'dsn'             => '',
        'params'          => [],
        'charset'         => 'utf8',
        'pk'              => '_id',
        'pk_type'         => 'ObjectID',
        'prefix'          => '',
        'deploy'          => 0,
        'rw_separate'     => false,
        'master_num'      => 1,
        'slave_no'        => '',
        'fields_strict'   => true,
        'fields_cache'    => false,
        'trigger_sql'     => true,
        'builder'         => '',
        'query'           => '',
        'auto_timestamp'  => false,
        'datetime_format' => 'Y-m-d H:i:s',
        'pk_convert_id'   => false,
        'type_map'        => ['root' => 'array', 'document' => 'array'],
        'timezone'        => '',
    ];

    public function getQueryClass(): string
    {
        return $this->getConfig('query') ?: Query::class;
    }

    public function getBuilder(): BuilderInterface
    {
        if ($this->builder === null) {
            $this->builder = new MongoBuilder($this);
        }
        return $this->builder;
    }

    public function createModelQuery(string $modelClass): BaseQuery
    {
        return new MongoModelQuery($this, $modelClass);
    }

    public function getBuilderClass(): string
    {
        return $this->getConfig('builder') ?: MongoBuilder::class;
    }

    public function connect(array $config = [], int $linkNum = 0): Manager
    {
        if (!isset($this->links[$linkNum])) {
            if (empty($config)) {
                $config = $this->config;
            } else {
                $config = array_merge($this->config, $config);
            }

            $this->dbName = $config['database'];
            $this->typeMap = $config['type_map'];

            if (empty($config['dsn'])) {
                $config['dsn'] = 'mongodb://' . ($config['username'] ? "{$config['username']}" : '') . ($config['password'] ? ":{$config['password']}@" : '') . $config['hostname'] . ($config['hostport'] ? ":{$config['hostport']}" : '');
                $config['dsn'] .= !empty($config['auth_source']) ? '/?authSource=' . $config['auth_source'] : '';
            }

            $startTime = microtime(true);

            $this->links[$linkNum] = new Manager($config['dsn'], $config['params']);

        if (!empty($this->config['trigger_sql'])) {
                $this->trigger(
                    'CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn']
                );
            }
        }

        return $this->links[$linkNum];
    }

    public function getMongo()
    {
        return $this->mongo ?: null;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function setDbName(string $db): void
    {
        $this->dbName = $db;
    }

    public function cursor($query)
    {
        $options = $query->parseOptions();

        $mongoQuery = $this->builder->select($query);
        $master = (bool)$query->getOption('master');

        return $this->getCursor($query, $mongoQuery, $master);
    }

    public function getCursor($query, $mongoQuery, bool $master = false): Cursor
    {
        $this->initConnect($master);

        $options = $query->getOptions();
        $namespace = $options['table'];

        if (!str_contains($namespace, '.')) {
            $namespace = $this->dbName . '.' . $namespace;
        }

        if ($mongoQuery instanceof Closure) {
            $mongoQuery = $mongoQuery($query);
        }

        $readPreference = $options['readPreference'] ?? null;

        if ($session = $this->getSession()) {
            $this->cursor = $this->mongo->executeQuery($namespace, $mongoQuery, [
                'readPreference' => is_null($readPreference) ? new ReadPreference(
                    ReadPreference::RP_PRIMARY
                ) : $readPreference,
                'session'        => $session,
            ]);
        } else {
            $this->cursor = $this->mongo->executeQuery($namespace, $mongoQuery, $readPreference);
        }

        return $this->cursor;
    }

    public function query(string $sql, array $bind = [], bool $master = false): array
    {
        throw new DbException('MongoDB 不支持原生 SQL 查询');
    }

    public function execute(string $sql, array $bind = []): int
    {
        throw new DbException('MongoDB 不支持原生 SQL 执行');
    }

    protected function mongoQuery($query, $mongoQuery): array
    {
        $options = $query->parseOptions();

        if ($mongoQuery instanceof Closure) {
            $mongoQuery = $mongoQuery($query);
        }

        $master = (bool)$query->getOption('master');
        $this->getCursor($query, $mongoQuery, $master);

        return $this->getResult($options['typeMap']);
    }

    protected function mongoExecute($query, BulkWrite $bulk)
    {
        $this->initConnect(true);

        $options = $query->getOptions();
        $namespace = $options['table'];

        if (!str_contains($namespace, '.')) {
            $namespace = $this->dbName . '.' . $namespace;
        }

        $writeConcern = $options['writeConcern'] ?? null;

        if ($session = $this->getSession()) {
            $writeResult = $this->mongo->executeBulkWrite($namespace, $bulk, [
                'session'      => $session,
                'writeConcern' => is_null($writeConcern) ? new WriteConcern(1) : $writeConcern,
            ]);
        } else {
            $writeResult = $this->mongo->executeBulkWrite($namespace, $bulk, $writeConcern);
        }

        return $writeResult;
    }

    public function command(
        Command $command,
        string $dbName = '',
        ?ReadPreference $readPreference = null,
        $typeMap = null,
        bool $master = false
    ): array {
        $this->initConnect($master);

        $dbName = $dbName ?: $this->dbName;

        if ($session = $this->getSession()) {
            $this->cursor = $this->mongo->executeCommand($dbName, $command, [
                'readPreference' => is_null($readPreference) ? new ReadPreference(
                    ReadPreference::RP_PRIMARY
                ) : $readPreference,
                'session'        => $session,
            ]);
        } else {
            $this->cursor = $this->mongo->executeCommand($dbName, $command, $readPreference);
        }

        return $this->getResult($typeMap);
    }

    protected function getResult($typeMap = null): array
    {
        if (is_null($typeMap)) {
            $typeMap = $this->typeMap;
        }

        $typeMap = is_string($typeMap) ? ['root' => $typeMap] : $typeMap;

        $this->cursor->setTypeMap($typeMap);

        $result = $this->cursor->toArray();

        if ($this->getConfig('pk_convert_id')) {
            foreach ($result as $key => $data) {
                $result[$key] = $this->convertObjectID($data);
            }
        }

        return $result;
    }

    protected function convertObjectID(array $data): array
    {
        if (isset($data['_id']) && is_object($data['_id'])) {
            $data['id'] = $data['_id']->__toString();
            unset($data['_id']);
        }

        return $data;
    }

    /**
     * 递归将数组中的 ObjectID 转为字符串（替代 array_walk_recursive 引用回调）
     */
    protected function convertIdsToStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof ObjectID) {
                $data[$key] = $value->__toString();
            } elseif (is_array($value)) {
                $data[$key] = $this->convertIdsToStrings($value);
            }
        }

        return $data;
    }

    public function mongoLog(string $type, $data, array $options = [])
    {
        if (!$this->config['trigger_sql']) {
            return;
        }

        if (is_array($data)) {
            $data = $this->convertIdsToStrings($data);
        }

        switch (strtolower($type)) {
            case 'aggregate':
                $this->queryStr = 'runCommand(' . ($data ? json_encode($data) : '') . ');';
                break;
            case 'find':
                $this->queryStr = $type . '(' . ($data ? json_encode($data) : ')');

                if (isset($options['sort'])) {
                    $this->queryStr .= '.sort(' . json_encode($options['sort']) . ')';
                }

                if (isset($options['skip'])) {
                    $this->queryStr .= '.skip(' . $options['skip'] . ')';
                }

                if (isset($options['limit'])) {
                    $this->queryStr .= '.limit(' . $options['limit'] . ')';
                }

                $this->queryStr .= ';';
                break;
            case 'insert':
            case 'remove':
                $this->queryStr = $type . '(' . ($data ? json_encode($data) : '') . ');';
                break;
            case 'update':
                $this->queryStr = $type . '(' . json_encode($options) . ',' . json_encode($data) . ');';
                break;
            case 'cmd':
                $this->queryStr = $data . '(' . json_encode($options) . ');';
                break;
        }
    }

    public function getLastSql(): string
    {
        return $this->queryStr;
    }

    public function close()
    {
        $this->mongo = null;
        $this->cursor = null;
        $this->linkRead = null;
        $this->linkWrite = null;
        $this->links = [];
    }

    protected function initConnect(bool $master = true): void
    {
        if (!empty($this->config['deploy'])) {
            if ($master) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }
                $this->mongo = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }
                $this->mongo = $this->linkRead;
            }
        } elseif (!$this->mongo) {
            $this->mongo = $this->connect();
        }
    }

    protected function multiConnect(bool $master = false): Manager
    {
        $config = [];
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn'] as $name) {
            $config[$name] = is_string($this->config[$name]) ? explode(
                ',',
                $this->config[$name]
            ) : $this->config[$name];
        }

        // 防御：master_num/主机数为 0 或越界时 mt_rand(min > max) 抛 ValueError
        $total = count($config['hostname']);
        $masterNum = max(0, min($this->config['master_num'] ?? 1, $total));
        $m = $masterNum > 0 ? floor(mt_rand(0, $masterNum - 1)) : 0;

        if ($this->config['rw_separate']) {
            if ($master) {
                if ($this->config['is_replica_set']) {
                    return $this->replicaSetConnect();
                } else {
                    $r = $m;
                }
            } elseif (is_numeric($this->config['slave_no'])) {
                $r = $this->config['slave_no'];
            } else {
                $from = min($masterNum, max(0, $total - 1));
                $r = floor(mt_rand($from, max($from, $total - 1)));
            }
        } else {
            $r = $total > 0 ? floor(mt_rand(0, $total - 1)) : 0;
        }

        $dbConfig = [];

        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn'] as $name) {
            $dbConfig[$name] = $config[$name][$r] ?? $config[$name][0];
        }

        return $this->connect($dbConfig, $r);
    }

    public function replicaSetConnect(): Manager
    {
        $this->dbName = $this->config['database'];
        $this->typeMap = $this->config['type_map'];
        $startTime = microtime(true);

        $this->config['params']['replicaSet'] = $this->config['database'];

        $manager = new Manager($this->buildUrl(), $this->config['params']);

        if (!empty($this->config['trigger_sql'])) {
            $this->trigger(
                'CONNECT:ReplicaSet[ UseTime:' . number_format(
                    microtime(true) - $startTime,
                    6
                ) . 's ] ' . $this->config['dsn']
            );
        }

        return $manager;
    }

    private function buildUrl(): string
    {
        $url = 'mongodb://' . ($this->config['username'] ? "{$this->config['username']}" : '') . ($this->config['password'] ? ":{$this->config['password']}@" : '');

        $hostList = is_string($this->config['hostname']) ? explode(
            ',',
            $this->config['hostname']
        ) : $this->config['hostname'];
        $portList = is_string($this->config['hostport']) ? explode(
            ',',
            $this->config['hostport']
        ) : $this->config['hostport'];

        for ($i = 0; $i < count($hostList); $i++) {
            // 端口逐主机配对：多主机时各用对应端口，缺省回退第一个（防所有主机共用同一端口）
            $port = $portList[$i] ?? $portList[0];
            $url = $url . $hostList[$i] . ':' . $port . ',';
        }

        return rtrim($url, ',') . '/';
    }

    public function insert($query, bool $getLastInsertId = false)
    {
        $options = $query->parseOptions();

        if (empty($options['data'])) {
            throw new DbException('miss data to insert');
        }

/** @var MongoBuilder $builder Mongo 连接固定使用 MongoBuilder */
        $builder = $this->builder;
        $bulk = $builder->insert($query);
        $writeResult = $this->mongoExecute($query, $bulk);

        $result = $writeResult->getInsertedCount();

        if ($result) {
            $data = $options['data'];
            $lastInsId = $this->getLastInsertId($query);

            if ($lastInsId) {
                $pk = $query->getPrimaryKey();
                $data[$pk] = $lastInsId;
            }

            $query->setOption('data', $data);

            if ($getLastInsertId) {
                return $lastInsId;
            }
        }

        return $result;
    }

    public function getLastInsertId(\yuandian\Database\Db\BaseQuery $query, ?string $sequence = null)
    {
        /** @var MongoBuilder $builder Mongo 连接固定使用 MongoBuilder */
        $builder = $this->builder;
        $id = $builder->getLastInsertId();

        if (is_array($id)) {
            foreach ($id as $key => $item) {
                if ($item instanceof ObjectID) {
                    $id[$key] = $item->__toString();
                }
            }
            return implode(',', $id);
        } elseif ($id instanceof ObjectID) {
            return $id->__toString();
        }

        return (string)$id;
    }

    public function insertAll($query, array $dataSet = []): int
    {
        $query->parseOptions();

        if (!is_array(reset($dataSet))) {
            return 0;
        }

        /** @var MongoBuilder $builder Mongo 连接固定使用 MongoBuilder */
        $builder = $this->builder;
        $bulk = $builder->insertAll($query, $dataSet);
        $writeResult = $this->mongoExecute($query, $bulk);

        return $writeResult->getInsertedCount();
    }

    public function update($query): int
    {
        $query->parseOptions();

        /** @var MongoBuilder $builder Mongo 连接固定使用 MongoBuilder */
        $builder = $this->builder;
        $bulk = $builder->update($query);
        $writeResult = $this->mongoExecute($query, $bulk);

        return $writeResult->getModifiedCount();
    }

    public function delete($query): int
    {
        $query->parseOptions();

        /** @var MongoBuilder $builder Mongo 连接固定使用 MongoBuilder */
        $builder = $this->builder;
        $bulk = $builder->delete($query);
        $writeResult = $this->mongoExecute($query, $bulk);

        return $writeResult->getDeletedCount();
    }

    public function select($query): array
    {
        return $this->mongoQuery($query, function ($query) {
            return $this->builder->select($query);
        });
    }

    public function find($query): array
    {
        $resultSet = $this->mongoQuery($query, function ($query) {
            return $this->builder->select($query, true);
        });

        return $resultSet[0] ?? [];
    }

    public function value($query, string $field, $default = null)
    {
        $options = $query->parseOptions();

        if (isset($options['projection'])) {
            $query->removeOption('projection');
        }

        $query->setOption('projection', (array)$field);

        $mongoQuery = $this->builder->select($query, true);

        if (isset($options['projection'])) {
            $query->setOption('projection', $options['projection']);
        } else {
            $query->removeOption('projection');
        }

        $resultSet = $this->mongoQuery($query, $mongoQuery);

        if (!empty($resultSet)) {
            $data = array_shift($resultSet);
            $result = $data[$field];
        } else {
            $result = false;
        }

        return false !== $result ? $result : $default;
    }

    public function column($query, string|array $field, string $key = ''): array
    {
        $options = $query->parseOptions();

        if (isset($options['projection'])) {
            $query->removeOption('projection');
        }

        if (is_array($field)) {
            $field = implode(',', $field);
        }
        if ($key && '*' != $field) {
            $projection = $key . ',' . $field;
        } else {
            $projection = $field;
        }

        $query->field($projection);

        $mongoQuery = $this->builder->select($query);

        if (isset($options['projection'])) {
            $query->setOption('projection', $options['projection']);
        } else {
            $query->removeOption('projection');
        }

        $resultSet = $this->mongoQuery($query, $mongoQuery);

        if (('*' == $field || str_contains($field, ',')) && $key) {
            $result = array_column($resultSet, null, $key);
        } elseif (!empty($resultSet)) {
            $result = array_column($resultSet, $field, $key);
        } else {
            $result = [];
        }

        return $result;
    }

    public function runCommand($query, $command, $extra = null, string $db = ''): array
    {
        if (is_array($command) || is_object($command)) {
            $this->mongoLog('cmd', 'cmd', $command);
            $command = new Command($command);
        } else {
            $command = $this->builder->$command($query, $extra);
        }

        return $this->command($command, $db);
    }

    public function getTableFields($tableName): array
    {
        return [];
    }

    public function transaction(callable $callback): mixed
    {
        $this->startTrans();

        try {
            $result = null;
            if (is_callable($callback)) {
                $result = call_user_func_array($callback, [$this]);
            }
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function startTrans(): void
    {
        $this->initConnect(true);
        $this->sessionUuid = uniqid();
        $this->sessions[$this->sessionUuid] = $this->getMongo()->startSession();

        $this->sessions[$this->sessionUuid]->startTransaction([]);
    }

    public function commit(): void
    {
        if ($session = $this->getSession()) {
            $session->commitTransaction();
            $this->setLastSession();
        }
    }

    public function rollback(): void
    {
        if ($session = $this->getSession()) {
            $session->abortTransaction();
            $this->setLastSession();
        }
    }

    protected function setLastSession()
    {
        if ($session = $this->getSession()) {
            $session->endSession();
            unset($this->sessions[$this->sessionUuid]);
            if (empty($this->sessions)) {
                $this->sessionUuid = null;
            } else {
                end($this->sessions);
                $this->sessionUuid = key($this->sessions);
            }
        }
    }

    public function getSession()
    {
        return ($this->sessionUuid && isset($this->sessions[$this->sessionUuid]))
            ? $this->sessions[$this->sessionUuid]
            : null;
    }

    public function disconnect(): void
    {
        $this->close();
    }
}
