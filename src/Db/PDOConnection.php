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
 *   - 断线重连（isConnectionBroken + reConnectTimes）
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
    protected ?PDO $linkId = null;

    /** @var PDO|null 读连接 */
    protected ?PDO $linkRead = null;

    /** @var PDO|null 写连接 */
    protected ?PDO $linkWrite = null;

    /** @var PDOStatement|null 当前语句 */
    protected ?PDOStatement $pdoStatement = null;

    protected string $queryStr = '';
    protected int $numRows = 0;
    protected int $transTimes = 0;
    protected int $reconnectTimes = 0;
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

    /** 允许的字符集白名单（防止 SET NAMES 注入） */
    private const CHARSET_WHITELIST = [
        'utf8', 'utf8mb4', 'utf8mb4_0900_ai_ci',
        'latin1', 'latin1_swedish_ci',
        'gbk', 'gb2312', 'gb18030',
        'big5', 'binary',
        'ascii', 'cp1252',
    ];

    protected function createPdo(string $dsn, string $username, string $password, array $params): PDO
    {
        $pdo = new PDO($dsn, $username, $password, $params);

        $charset = $this->config['charset'] ?? 'utf8mb4';
        // SQLite 字符集内建为 UTF-8，不支持 SET NAMES 语句
        if (($this->config['type'] ?? '') !== 'sqlite') {
            if (!in_array($charset, self::CHARSET_WHITELIST, true)) {
                throw new \yuandian\Database\Exceptions\DbException(
                    "不支持的字符集: '{$charset}'，允许: " . implode(', ', self::CHARSET_WHITELIST)
                );
            }
            $pdo->exec("SET NAMES '{$charset}'");
        }

        return $pdo;
    }

    public function close(): static
    {
        $this->linkId = null;
        $this->linkWrite = null;
        $this->linkRead = null;
        $this->links = [];
        $this->info = [];
        $this->transTimes = 0;
        $this->pdoStatement = null;

        return $this;
    }

    /**
     * 清除 schema 缓存
     *
     * @param string|null $schema 指定 schema 名清除，null 清除全部
     */
    public function clearSchemaCache(?string $schema = null): void
    {
        if ($schema === null) {
            $this->info = [];
        } else {
            unset($this->info[$schema]);
        }
    }

    // ======================== SQL 执行 ========================

    public function query(string $sql, array $bind = [], bool $master = false): array
    {
        $this->getPdoStatement($sql, $bind, $master);
        return $this->getResult();
    }

    public function execute(string $sql, array $bind = []): int
    {
        $this->getPdoStatement($sql, $bind, true);
        return $this->pdoStatement->rowCount();
    }

    public function getPdoStatement(string $sql, array $bind = [], bool $master = false): PDOStatement
    {
        try {
            $this->initConnect($master);
            $this->queryStr = $sql;
            $this->bind = $bind;
            $this->queryStartTime = microtime(true);

            $this->pdoStatement = $this->linkId->prepare($sql);
            $this->bindValue($bind);
            $this->pdoStatement->execute();

            // SQL 监控：存在监听器时才构建最终 SQL（无监听器短路，避免 getRealSql 开销）
            if (!empty($this->config['trigger_sql']) && $this->hasSqlListener()) {
                $this->triggerSql('', $master);
            }

            $this->reconnectTimes = 0;
            return $this->pdoStatement;
        } catch (\Throwable $e) {
            if ($this->transTimes > 0) {
                if ($this->isConnectionBroken($e)) {
                    $this->transTimes = 0;
                }
            } else {
                if ($this->reconnectTimes < 4 && $this->isConnectionBroken($e)) {
                    $this->reconnectTimes++;
                    return $this->close()->getPdoStatement($sql, $bind, $master);
                }
            }
            throw $e;
        }
    }

    protected function getResult(): array
    {
        $result = $this->pdoStatement->fetchAll($this->fetchType);
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
                $this->linkId = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }
                $this->linkId = $this->linkRead;
            }
        } elseif (!$this->linkId) {
            $this->linkId = $this->connect();
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
                // 防御：master_num 越界时 mt_rand(min > max) 抛 ValueError
                $total = count($config['hostname']);
                $from  = min($this->config['master_num'] ?? 1, max(0, $total - 1));
                $r = mt_rand($from, max($from, $total - 1));
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
                $this->pdoStatement->bindValue($param, $val[0], $val[1]);
            } else {
                $this->pdoStatement->bindValue($param, $val);
            }
        }
    }

    // ======================== 事务 ========================

    public function startTrans(): void
    {
        $this->initConnect(true);

        if (0 == $this->transTimes) {
            $this->linkId->beginTransaction();
        } elseif ($this->transTimes > 0 && $this->supportSavepoint() && $this->linkId->inTransaction()) {
            $this->linkId->exec($this->parseSavepoint('trans' . ($this->transTimes + 1)));
        }
        $this->transTimes++;
        $this->reconnectTimes = 0;
    }

    public function commit(): void
    {
        $this->initConnect(true);
        $this->transTimes = max(0, $this->transTimes - 1);

        if (0 == $this->transTimes && $this->linkId->inTransaction()) {
            $this->linkId->commit();
        }
    }

    public function rollback(): void
    {
        $this->initConnect(true);
        $this->transTimes = max(0, $this->transTimes - 1);

        if ($this->linkId->inTransaction()) {
            if (0 == $this->transTimes) {
                $this->linkId->rollBack();
            } elseif ($this->transTimes > 0 && $this->supportSavepoint()) {
                $this->linkId->exec($this->parseSavepointRollback('trans' . ($this->transTimes + 1)));
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

    protected function parseSavepointRollback(string $name): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    // ======================== 断线检测 ========================

    protected function isConnectionBroken(\Throwable $e): bool
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

    public function getTableInfo(string $tableName, string $fetch = ''): mixed
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

    public function getPrimaryKey(string $tableName): string|array|null
    {
        return $this->getTableInfo($tableName, 'pk');
    }

    public function getAutoIncrement(string $tableName): ?string
    {
        return $this->getTableInfo($tableName, 'autoinc');
    }

    // ======================== 工具方法 ========================

    public function getPdo(): PDO|false
    {
        return $this->linkId ?: false;
    }

    public function getLastSql(): string
    {
        return $this->getRealSql($this->queryStr, $this->bind);
    }

    public function getLastInsertId(BaseQuery $query, ?string $sequence = null): string|false
    {
        return $this->linkId ? $this->linkId->lastInsertId() : '';
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

            if (is_numeric($key)) {
                // 防御：bind 数量多于 SQL 占位符时 strpos 返回 false，跳过避免位置 0 错误替换
                $pos = strpos($sql, '?');
                if ($pos !== false) {
                    $sql = substr_replace($sql, $value, $pos, 1);
                }
            } else {
                $sql = str_replace(
                    [':' . $key . ' ', ':' . $key . ',', ':' . $key . ')'],
                    [$value . ' ', $value . ',', $value . ')'],
                    $sql . ' '
                );
            }
        }

        return rtrim($sql);
    }

    public function getNumRows(): int
    {
        return $this->numRows;
    }

    public function getError(): string
    {
        if ($this->pdoStatement) {
            $error = $this->pdoStatement->errorInfo();
            return ($error[1] ?? '') . ':' . ($error[2] ?? '');
        }
        return '';
    }
}
