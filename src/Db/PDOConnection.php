<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use PDO;
use PDOStatement;
use yuandian\Database\Exceptions\DbException;

/**
 * PDO 连接抽象层
 *
 * 封装所有 PDO 细节，提供数据库无关的连接能力
 *
 * 核心能力：
 *   - 连接池（$links[]）
 *   - 读写分离（$linkRead / $linkWrite）
 *   - 断线重连（isBreak + reConnectTimes）
 *   - 参数绑定（bindValue / bindParam）
 *   - Schema 缓存（getSchemaInfo + $info[]）
 *   - SQL 监控（trigger + queryStartTime）
 *   - 事务嵌套（supportSavepoint）
 */
abstract class PDOConnection extends Connection
{
    const PARAM_INT = 1;
    const PARAM_STR = 2;
    const PARAM_BOOL = 5;
    const PARAM_FLOAT = 21;

    /** @var PDO[] 连接池 */
    protected array $links = [];

    /** @var PDO|null 当前连接 */
    protected ?PDO $linkID = null;

    /** @var PDO|null 读连接 */
    protected ?PDO $linkRead = null;

    /** @var PDO|null 写连接 */
    protected ?PDO $linkWrite = null;

    /** @var PDOStatement|null 当前语句 */
    protected ?PDOStatement $PDOStatement = null;

    protected string $queryStr = '';
    protected int $numRows = 0;
    protected int $transTimes = 0;
    protected int $reConnectTimes = 0;
    protected float $queryStartTime = 0;
    protected array $bind = [];
    protected array $info = [];

    /** @var int 结果集获取方式 */
    protected int $fetchType = PDO::FETCH_ASSOC;

    /** @var int 字段大小写 */
    protected int $attrCase = PDO::CASE_LOWER;

    /** 默认配置 */
    protected array $config = [
        'type'            => '',
        'hostname'        => '',
        'database'        => '',
        'username'        => '',
        'password'        => '',
        'hostport'        => '',
        'dsn'             => '',
        'params'          => [],
        'charset'         => 'utf8',
        'prefix'          => '',
        'deploy'          => 0,
        'rw_separate'     => false,
        'master_num'      => 1,
        'slave_no'        => '',
        'read_master'     => false,
        'fields_strict'   => true,
        'fields_cache'    => false,
        'trigger_sql'     => true,
        'builder'         => '',
        'query'           => '',
        'break_reconnect' => false,
        'break_match_str' => [],
        'auto_param_bind' => true,
        'timezone'        => '',
    ];

    /** PDO 默认参数 */
    protected array $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];

    /** 参数绑定类型映射 */
    protected array $bindType = [
        'string'    => self::PARAM_STR,
        'str'       => self::PARAM_STR,
        'bigint'    => self::PARAM_STR,
        'set'       => self::PARAM_STR,
        'enum'      => self::PARAM_STR,
        'integer'   => self::PARAM_INT,
        'int'       => self::PARAM_INT,
        'boolean'   => self::PARAM_BOOL,
        'bool'      => self::PARAM_BOOL,
        'float'     => self::PARAM_FLOAT,
        'datetime'  => self::PARAM_STR,
        'date'      => self::PARAM_STR,
        'timestamp' => self::PARAM_STR,
    ];

    /** 断线标识 */
    protected array $breakMatchStr = [
        'server has gone away',
        'no connection to the server',
        'Lost connection',
        'is dead or not enabled',
        'Error while sending',
        'decryption failed or bad record mac',
        'server closed the connection unexpectedly',
        'SSL connection has been closed unexpectedly',
        'Error writing data to the connection',
        'Resource deadlock avoided',
        'failed with errno',
        'child connection forced to terminate due to client_idle_limit',
        'query_wait_timeout',
        'reset by peer',
        'Physical connection is not usable',
        'TCP Provider: Error code 0x68',
        'ORA-03114',
        'Packets out of order. Expected',
        'Adaptive Server connection failed',
        'Communication link failure',
        'connection is no longer usable',
        'Login timeout expired',
        'SQLSTATE[HY000] [2002] Connection refused',
        'running with the --read-only option so it cannot execute this statement',
        'The connection is broken and recovery is not possible.',
        'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed',
        'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
        'SQLSTATE[HY000] [2002] Connection timed out',
        'SSL: Connection timed out',
    ];

    // ======================== 抽象方法 ========================

    /**
     * 解析 DSN
     */
    abstract protected function parseDsn(array $config): string;

    /**
     * 获取表字段信息
     */
    abstract public function getFields(string $tableName): array;

    /**
     * 获取表列表
     */
    abstract public function getTables(string $dbName = ''): array;

    // ======================== 连接管理 ========================

    public function connect(array $config = [], int $linkNum = 0): PDO|static
    {
        if (isset($this->links[$linkNum])) {
            return $this->links[$linkNum];
        }

        if (empty($config)) {
            $config = $this->config;
        } else {
            $config = array_merge($this->config, $config);
        }

        // 合并连接参数
        $params = isset($config['params']) && is_array($config['params'])
            ? $config['params'] + $this->params
            : $this->params;

        $this->attrCase = $params[PDO::ATTR_CASE] ?? PDO::CASE_NATURAL;

        if (!empty($config['break_match_str'])) {
            $this->breakMatchStr = array_merge($this->breakMatchStr, (array)$config['break_match_str']);
        }

        try {
            if (empty($config['dsn'])) {
                $config['dsn'] = $this->parseDsn($config);
            }

            $startTime = microtime(true);
            $this->links[$linkNum] = $this->createPdo(
                $config['dsn'],
                $config['username'] ?? '',
                $config['password'] ?? '',
                $params
            );

            // SQL 监控
            if (!empty($config['trigger_sql'])) {
                $this->triggerSql(
                    'CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn']
                );
            }

            return $this->links[$linkNum];
        } catch (\PDOException $e) {
            throw new DbException('数据库连接失败: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function createPdo(string $dsn, string $username, string $password, array $params): PDO
    {
        $pdo = new PDO($dsn, $username, $password, $params);

        $charset = $this->config['charset'] ?? 'utf8mb4';
        $pdo->exec("SET NAMES '{$charset}'");

        return $pdo;
    }

    public function close()
    {
        $this->linkID = null;
        $this->linkWrite = null;
        $this->linkRead = null;
        $this->links = [];
        $this->transTimes = 0;
        $this->PDOStatement = null;

        return $this;
    }

    // ======================== SQL 执行 ========================

    public function query(string $sql, array $bind = [], bool $master = false): array
    {
        $this->getPDOStatement($sql, $bind, $master);
        return $this->getResult();
    }

    public function execute(string $sql, array $bind = []): int
    {
        $this->getPDOStatement($sql, $bind, true);
        return $this->PDOStatement->rowCount();
    }

    /**
     * 获取 PDOStatement
     */
    public function getPDOStatement(string $sql, array $bind = [], bool $master = false): PDOStatement
    {
        try {
            $this->initConnect($master);
            $this->queryStr = $sql;
            $this->bind = $bind;
            $this->queryStartTime = microtime(true);

            $this->PDOStatement = $this->linkID->prepare($sql);
            $this->bindValue($bind);
            $this->PDOStatement->execute();

            // SQL 监控
            if (!empty($this->config['trigger_sql'])) {
                $this->triggerSql('', $master);
            }

            $this->reConnectTimes = 0;
            return $this->PDOStatement;
        } catch (\Throwable $e) {
            if ($this->transTimes > 0) {
                if ($this->isBreak($e)) {
                    $this->transTimes = 0;
                }
            } else {
                if ($this->reConnectTimes < 4 && $this->isBreak($e)) {
                    $this->reConnectTimes++;
                    return $this->close()->getPDOStatement($sql, $bind, $master);
                }
            }
            throw $e;
        }
    }

    /**
     * 获取结果集
     */
    protected function getResult(): array
    {
        $result = $this->PDOStatement->fetchAll($this->fetchType);
        $this->numRows = count($result);
        return $result;
    }

    // ======================== 连接初始化 ========================

    /**
     * 初始化连接（支持读写分离）
     */
    protected function initConnect(bool $master = false): void
    {
        if (!empty($this->config['deploy'])) {
            // 分布式数据库
            if ($master || $this->transTimes) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }
                $this->linkID = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }
                $this->linkID = $this->linkRead;
            }
        } elseif (!$this->linkID) {
            $this->linkID = $this->connect();
        }
    }

    /**
     * 分布式连接
     */
    protected function multiConnect(bool $master = false): PDO
    {
        // 简化实现：随机选择从库
        $config = [];
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $config[$name] = is_string($this->config[$name] ?? '')
                ? explode(',', $this->config[$name])
                : [$this->config[$name] ?? ''];
        }

        $m = 0; // 主库索引
        if ($this->config['rw_separate']) {
            if ($master) {
                $r = $m;
            } elseif (is_numeric($this->config['slave_no'] ?? '')) {
                $r = (int)$this->config['slave_no'];
            } else {
                $r = mt_rand($this->config['master_num'] ?? 1, count($config['hostname']) - 1);
            }
        } else {
            $r = mt_rand(0, count($config['hostname']) - 1);
        }

        $dbConfig = [];
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $dbConfig[$name] = $config[$name][$r] ?? $config[$name][0];
        }

        return $this->connect($dbConfig, $r);
    }

    // ======================== 参数绑定 ========================

    /**
     * 绑定值
     */
    protected function bindValue(array $bind = []): void
    {
        foreach ($bind as $key => $val) {
            $param = is_numeric($key) ? $key + 1 : ':' . $key;

            if (is_array($val)) {
                if (self::PARAM_INT == $val[1]) {
                    $val[0] = (int)$val[0];
                } elseif (self::PARAM_FLOAT == $val[1]) {
                    $val[0] = is_string($val[0]) ? (float)$val[0] : $val[0];
                    $val[1] = self::PARAM_STR;
                }
                $this->PDOStatement->bindValue($param, $val[0], $val[1]);
            } else {
                $this->PDOStatement->bindValue($param, $val);
            }
        }
    }

    // ======================== 事务 ========================

    public function startTrans(): void
    {
        $this->initConnect(true);

        if (0 == $this->transTimes) {
            $this->linkID->beginTransaction();
        } elseif ($this->transTimes > 0 && $this->supportSavepoint() && $this->linkID->inTransaction()) {
            $this->linkID->exec($this->parseSavepoint('trans' . ($this->transTimes + 1)));
        }
        $this->transTimes++;
        $this->reConnectTimes = 0;
    }

    public function commit(): void
    {
        $this->initConnect(true);
        $this->transTimes = max(0, $this->transTimes - 1);

        if (0 == $this->transTimes && $this->linkID->inTransaction()) {
            $this->linkID->commit();
        }
    }

    public function rollback(): void
    {
        $this->initConnect(true);
        $this->transTimes = max(0, $this->transTimes - 1);

        if ($this->linkID->inTransaction()) {
            if (0 == $this->transTimes) {
                $this->linkID->rollBack();
            } elseif ($this->transTimes > 0 && $this->supportSavepoint()) {
                $this->linkID->exec($this->parseSavepointRollBack('trans' . ($this->transTimes + 1)));
            }
        }
    }

    public function transaction(callable $callback): mixed
    {
        $this->startTrans();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    protected function supportSavepoint(): bool
    {
        return false;
    }

    protected function parseSavepoint(string $name): string
    {
        return 'SAVEPOINT ' . $name;
    }

    protected function parseSavepointRollBack(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    // ======================== 断线检测 ========================

    protected function isBreak(\Throwable $e): bool
    {
        if (!($this->config['break_reconnect'] ?? false)) {
            return false;
        }

        $error = $e->getMessage();
        foreach ($this->breakMatchStr as $msg) {
            if (false !== stripos($error, $msg)) {
                return true;
            }
        }
        return false;
    }

    // ======================== SQL 监控 ========================

    protected function triggerSql(string $sql = '', bool $master = false): void
    {
        $runtime = number_format((microtime(true) - $this->queryStartTime), 6);
        $sql = $sql ?: $this->getLastSql();

        if (empty($this->config['deploy'])) {
            $master = null;
        }

        $this->trigger('sql', $sql, $runtime, $master);
    }

    // ======================== Schema ========================

    public function getTableFields(string $tableName): array
    {
        return $this->getTableInfo($tableName, 'fields');
    }

    public function getTableInfo(string $tableName, string $fetch = '')
    {
        $info = $this->getSchemaInfo($tableName);
        return $fetch && array_key_exists($fetch, $info) ? $info[$fetch] : $info;
    }

    public function getSchemaInfo(string $tableName, bool $force = false): array
    {
        $schema = $this->getConfig('database') . '.' . $tableName;

        if (isset($this->info[$schema]) && !$force) {
            return $this->info[$schema];
        }

        $fields = $this->getFields($tableName);
        $info = [];
        $pk = null;
        $autoinc = null;

        foreach ($fields as $key => $val) {
            $info[$key] = $this->getFieldType($val['type'] ?? 'string');
            if (!empty($val['primary'])) {
                $pk[] = $key;
            }
            if (!empty($val['autoinc'])) {
                $autoinc = $key;
            }
        }

        if (isset($pk)) {
            $pk = count($pk) > 1 ? $pk : $pk[0];
            $info['_pk'] = $pk;
        }

        if (isset($autoinc)) {
            $info['_autoinc'] = $autoinc;
        }

        $this->info[$schema] = [
            'fields'  => array_keys($info),
            'type'    => $info,
            'bind'    => array_map(fn($val) => $this->getFieldBindType($val), $info),
            'pk'      => $pk ?? null,
            'autoinc' => $autoinc ?? null,
        ];

        return $this->info[$schema];
    }

    protected function getFieldType(string $type): string
    {
        $type = strtolower($type);
        return match (true) {
            str_starts_with($type, 'set') => 'set',
            str_starts_with($type, 'enum') => 'enum',
            str_starts_with($type, 'bigint') => 'bigint',
            str_contains($type, 'float') || str_contains($type, 'double') => 'float',
            str_contains($type, 'int') || str_contains($type, 'serial') => 'int',
            str_contains($type, 'bool') => 'bool',
            str_contains($type, 'json') => 'json',
            str_starts_with($type, 'timestamp') => 'timestamp',
            str_starts_with($type, 'datetime') => 'datetime',
            str_starts_with($type, 'date') => 'date',
            default => 'string',
        };
    }

    public function getFieldBindType(string $type): int
    {
        return $this->bindType[$type] ?? self::PARAM_STR;
    }

    public function getPk(string $tableName)
    {
        return $this->getTableInfo($tableName, 'pk');
    }

    public function getAutoInc(string $tableName)
    {
        return $this->getTableInfo($tableName, 'autoinc');
    }

    // ======================== 工具方法 ========================

    public function getPdo()
    {
        return $this->linkID ?: false;
    }

    public function getLastSql(): string
    {
        return $this->getRealSql($this->queryStr, $this->bind);
    }

    public function getLastInsID(BaseQuery $query, ?string $sequence = null)
    {
        return $this->linkID ? $this->linkID->lastInsertId() : '';
    }

    public function find(BaseQuery $query): array
    {
        $query->parseOptions();

        $options = $query->getOptions();
        $options['limit'] = 1;

        [$sql, $bind] = $this->getBuilder()->select($options);
        $result = $this->query($sql, $bind);

        return $result[0] ?? [];
    }

    public function select(BaseQuery $query): array
    {
        $query->parseOptions();

        $options = $query->getOptions();
        [$sql, $bind] = $this->getBuilder()->select($options);

        return $this->query($sql, $bind);
    }

    public function insert(BaseQuery $query, bool $getLastInsID = false)
    {
        $query->parseOptions();

        $options = $query->getOptions();
        $data = $options['data'];

        [$sql, $bind] = $this->getBuilder()->insert($options['table'], $data);
        $this->execute($sql, $bind);

        $lastInsId = $this->getLastInsID();

        if ($getLastInsID) {
            return $lastInsId;
        }

        return $lastInsId ? 1 : 0;
    }

    public function insertAll(BaseQuery $query, array $dataSet = []): int
    {
        $query->parseOptions();

        $options = $query->getOptions();
        [$sql, $bind] = $this->getBuilder()->insertAll($options['table'], $dataSet);
        $this->execute($sql, $bind);

        return count($dataSet);
    }

    public function update(BaseQuery $query): int
    {
        $query->parseOptions();

        $options = $query->getOptions();
        $data = $options['data'];
        $where = $options['where'];

        [$sql, $bind] = $this->getBuilder()->update($options['table'], $data, $where);

        return $this->execute($sql, $bind);
    }

    public function delete(BaseQuery $query): int
    {
        $query->parseOptions();

        $options = $query->getOptions();
        $where = $options['where'];

        [$sql, $bind] = $this->getBuilder()->delete($options['table'], $where);

        return $this->execute($sql, $bind);
    }

    public function value(BaseQuery $query, string $field, $default = null)
    {
        $query->parseOptions();

        $options = $query->getOptions();
        $options['field'] = [$field];
        $options['limit'] = 1;

        [$sql, $bind] = $this->getBuilder()->select($options);
        $result = $this->query($sql, $bind);

        if (empty($result)) {
            return $default;
        }

        $row = $result[0];
        return reset($row);
    }

    public function column(BaseQuery $query, string|array $column, string $key = ''): array
    {
        $query->parseOptions();

        $options = $query->getOptions();
        $options['field'] = is_array($column) ? $column : [$column];

        [$sql, $bind] = $this->getBuilder()->select($options);
        $result = $this->query($sql, $bind);

        if (empty($result)) {
            return [];
        }

        if (is_array($column)) {
            return array_column($result, null, $key);
        }

        return array_column($result, $column, $key);
    }

    public function getRealSql(string $sql, array $bind = []): string
    {
        foreach ($bind as $key => $val) {
            $value = strval(is_array($val) ? $val[0] : $val);
            $type = is_array($val) ? $val[1] : self::PARAM_STR;

            if (self::PARAM_FLOAT == $type || self::PARAM_STR == $type) {
                $value = '\'' . addslashes($value) . '\'';
            } elseif (self::PARAM_INT == $type && '' === $value) {
                $value = '0';
            }

            $sql = is_numeric($key)
                ? substr_replace($sql, $value, strpos($sql, '?'), 1)
                : str_replace(
                    [':' . $key . ' ', ':' . $key . ',', ':' . $key . ')'],
                    [$value . ' ', $value . ',', $value . ')'],
                    $sql . ' '
                );
        }

        return rtrim($sql);
    }

    public function getNumRows(): int
    {
        return $this->numRows;
    }

    public function getError(): string
    {
        if ($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            return ($error[1] ?? '') . ':' . ($error[2] ?? '');
        }
        return '';
    }
}
