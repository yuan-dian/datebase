<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\DbManager;
use yuandian\Database\Model\ModelQuery;

/** @template TModel */
abstract class Connection implements ConnectionInterface, QueryContext
{
    protected array $config;
    protected ?BuilderInterface $builder = null;
    protected ?DbManager $db = null;

    /** @var array<string, callable[]> 事件监听器 */
    protected array $listen = [];

    public function __construct(array $config)
    {
        // 子类属性声明的默认配置（如 trigger_sql=true）先于构造就位，传入配置仅覆盖默认值
        $this->config = array_merge($this->config, $config);
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

    /**
     * 是否存在 SQL 事件监听器（连接级或 DbManager 级）
     *
     * 用于短路 SQL 监控字符串构建：无监听器时不必拼接最终 SQL。
     */
    protected function hasSqlListener(): bool
    {
        if (!empty($this->listen['sql'])) {
            return true;
        }

        return $this->db !== null && !empty($this->db->getListen()['sql']);
    }

    // ======================== 工厂方法 ========================

    /**
     * 获取查询构造器（单例）
     */
    public function getBuilder(): BuilderInterface
    {
        if ($this->builder === null) {
            $builderClass = $this->getBuilderClass();
            $this->builder = new $builderClass($this);
        }
        return $this->builder;
    }

    /**
     * 创建 Db 层查询实例（QueryContext 契约）
     *
     * @param string|null $table 数据表名
     * @return BaseQuery
     */
    public function newQuery(?string $table = null): BaseQuery
    {
        $queryClass = $this->getQueryClass();

        return new $queryClass($this, $table);
    }

    /**
     * 为 Model 层创建查询实例（子类可覆写以返回不同 Query 类）
     *
     * @param string $modelClass 模型类名
     * @return BaseQuery
     */
    public function createModelQuery(string $modelClass): BaseQuery
    {
        return new ModelQuery($this, $modelClass);
    }

    /**
     * 创建 Db 层查询实例（兼容别名，内部统一走 newQuery）
     *
     * @param string|null $table 数据表名
     * @return BaseQuery
     */
    public function table(?string $table = null): BaseQuery
    {
        return $this->newQuery($table);
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

    abstract public function getLastInsertId(BaseQuery $query, ?string $sequence = null);
}
