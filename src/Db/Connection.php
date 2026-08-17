<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\Db\Builder\Mongo as MongoBuilder;
use yuandian\Database\DbManager;

/** @template TModel */
abstract class Connection implements ConnectionInterface
{
    protected array $config;
    protected ?Builder $builder = null;
    protected ?DbManager $db = null;

    /** @var array<string, callable[]> 事件监听器 */
    protected array $listen = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    // ======================== DbManager 引用 ========================

    public function setDb(DbManager $db): void
    {
        $this->db = $db;
    }

    public function getDb(): ?DbManager
    {
        return $this->db;
    }

    // ======================== 事件系统 ========================

    /**
     * 注册事件监听器
     */
    public function listen(string $event, callable $callback): void
    {
        $this->listen[$event][] = $callback;
    }

    /**
     * 触发事件
     */
    public function trigger(string $event, ...$args): void
    {
        // 先触发 DbManager 的监听器
        if ($this->db) {
            $this->db->trigger($event, ...$args);
        }

        // 再触发连接级别的监听器
        if (!empty($this->listen[$event])) {
            foreach ($this->listen[$event] as $callback) {
                $callback(...$args);
            }
        }
    }

    // ======================== 工厂方法 ========================

    /**
     * 获取查询构造器（单例）
     */
    public function getBuilder(): Builder|MongoBuilder
    {
        if ($this->builder === null) {
            $builderClass = $this->getBuilderClass();
            $this->builder = new $builderClass($this);
        }
        return $this->builder;
    }

    /**
     * 创建 Db 层查询实例
     *
     * @param string|null $table 数据表名
     * @return BaseQuery
     */
    public function table(?string $table = null): BaseQuery
    {
        $queryClass = $this->getQueryClass();

        return new $queryClass($this, $table);
    }

    // ======================== 配置 ========================

    public function getConfig(string $key = ''): mixed
    {
        if ('' === $key) {
            return $this->config;
        }
        return $this->config[$key] ?? null;
    }

    public function getTablePrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    // ======================== 抽象方法 ========================

    abstract public function getQueryClass(): string;

    abstract public function getBuilderClass(): string;

    abstract public function getLastInsID(BaseQuery $query, ?string $sequence = null);
}
