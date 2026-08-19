<?php

declare(strict_types=1);

namespace yuandian\Database;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Db\Connection;
use yuandian\Database\Db\Connector\Mongo;
use yuandian\Database\Db\Connector\Mysql;
use yuandian\Database\Db\Connector\Oracle;
use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Exceptions\DbException;

/**
 * 数据库连接管理器
 *
 * 负责：
 *   1. 读取配置
 *   2. 创建 & 缓存连接实例
 *   3. 提供事务便捷方法
 */
class DbManager
{
    /** @var array<string, Connection> 连接实例缓存 */
    protected array $connections = [];

    /** @var array 配置缓存 */
    protected array $config = [];

    /** @var array<string, callable[]> 事件监听器 */
    protected array $listen = [];

    /** @var array 日志记录 */
    protected array $log = [];

    // ===================== 配置 =====================

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * 加载数据库配置
     */
    public function getConfig(?string $name = null, ?string $default = null): mixed
    {
        if ('' === $name) {
            return $this->config;
        }

        return $this->config[$name] ?? $default;
    }

    /**
     * 获取连接配置.
     *
     * @param string $name
     *
     * @return array
     */
    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->getConfig('connections');
        if (!isset($connections[$name])) {
            throw new DbException('Undefined db config:' . $name);
        }

        return $connections[$name];
    }

    /**
     * 获取默认数据源名
     */
    public function connect(string|array|null $name = null, bool $force = false): Connection
    {
        return $this->getConnection($name, $force);
    }

    /**
     * 便捷入口：直接按表名创建 Db 层查询实例
     */
    public function table(string $table): BaseQuery
    {
        return $this->connect()->table($table);
    }

    // ===================== 连接 =====================

    /**
     * 获取数据库连接实例（单例）
     */
    protected function getConnection(?string $name = null, bool $force = false): Connection
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'mysql');
        }

        if ($force || !isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * 根据类型创建连接实例
     */
    protected function createConnection(string|array $config): Connection
    {
        $config = is_array($config) ? $config : $this->getConnectionConfig($config);
        $type = $config['type'] ?? 'mysql';
        $connection = match (strtolower($type)) {
            'mysql' => new Mysql($config),
            'sqlite' => new Sqlite($config),
            'oracle' => new Oracle($config),
            'mongodb' => new Mongo($config),
            default => throw new DbException("不支持的数据库类型: {$type}"),
        };
        // 注入 DbManager 引用：连接级事件（如 DbManager::listen 注册的 sql 监听）依赖它
        $connection->setDb($this);
        return $connection;
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->connect(), $method], $args);
    }

    // ===================== 事务（显式转发，供 TypePHP 静态可见；__call 保留兜底） =====================

    public function transaction(callable $callback): mixed
    {
        return $this->connect()->transaction($callback);
    }

    public function startTrans(): void
    {
        $this->connect()->startTrans();
    }

    public function commit(): void
    {
        $this->connect()->commit();
    }

    public function rollback(): void
    {
        $this->connect()->rollback();
    }

    // ===================== 事件监听 =====================

    /**
     * 注册事件监听器
     */
    public function listen(string $event, callable $callback): void
    {
        if (!isset($this->listen[$event])) {
            $this->listen[$event] = [];
        }
        $this->listen[$event][] = $callback;
    }

    /**
     * 触发事件
     */
    public function trigger(string $event, ...$args): void
    {
        if (isset($this->listen[$event])) {
            foreach ($this->listen[$event] as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    /**
     * 获取事件监听器列表
     */
    public function getListen(): array
    {
        return $this->listen;
    }

    // ===================== 日志 =====================

    /**
     * 记录日志
     */
    public function log(string $message, string $level = 'info'): void
    {
        $this->log[] = [
            'time'    => date('Y-m-d H:i:s'),
            'level'   => $level,
            'message' => $message,
        ];
    }

    /**
     * 获取日志记录
     */
    public function getLog(): array
    {
        return $this->log;
    }
}
