<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Model\Model;

/**
 * 模型事件能力：before/after 生命周期事件 + 全局/模型级监听器。
 *
 * 事件列表（11个）：
 *  - beforeInsert / afterInsert
 *  - beforeUpdate / afterUpdate
 *  - beforeDelete / afterDelete
 *  - beforeForceDelete / afterForceDelete
 *  - beforeRestore / afterRestore
     *  - afterRead（查询后触发）
 *
 * 使用方式：
 *  1. 模型方法：onBeforeInsert() / onAfterInsert() 等（自动发现）
 *  2. 全局监听：Model::listen('beforeInsert', fn($m) => ...)
 *  3. 模型级监听：User::modelListen('beforeInsert', fn($m) => ...)
 *
 * @date 2026/8/21
 * @author 原点 <467490186@qq.com>
 */
trait HasEvents
{
    /** @var array<string, list<callable(Model): ?bool>> 全局监听器 */
    private static array $globalListeners = [];

    /** @var array<class-string, array<string, list<callable(Model): ?bool>>> 模型级监听器 */
    private static array $modelListeners = [];

    /**
     * 注册全局监听器（所有模型共享）
     *
     * @param string $event 事件名（如 'beforeInsert'）
     * @param callable(Model): ?bool $callback 返回 false 阻止操作
     */
    public static function listen(string $event, callable $callback): void
    {
        self::$globalListeners[$event][] = $callback;
    }

    /**
     * 注册当前模型级监听器（仅对当前模型生效）
     */
    public static function modelListen(string $event, callable $callback): void
    {
        self::$modelListeners[static::class][$event][] = $callback;
    }

    /**
     * 触发事件：模型方法 → 模型级监听器 → 全局监听器
     *
     * @return bool true=已阻止后续执行
     */
    protected function triggerEvent(string $event): bool
    {
        // 1. 模型方法 on{Event}（如 onBeforeInsert）— 使用 ModelMeta 缓存避免 method_exists 运行时开销
        $eventMethods = static::getMeta()->eventMethods;
        if ($eventMethods !== []) {
            $method = 'on' . ucfirst($event);
            if (in_array($method, $eventMethods, true)) {
                if ($this->$method() === false) {
                    return true;
                }
            }
        }

        // 2. 模型级监听器
        foreach (self::$modelListeners[static::class][$event] ?? [] as $callback) {
            if ($callback($this) === false) {
                return true;
            }
        }

        // 3. 全局监听器
        foreach (self::$globalListeners[$event] ?? [] as $callback) {
            if ($callback($this) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 触发 afterRead 事件（供 toModel 调用）
     *
     * triggerEvent 是 protected，toModel 在 ModelQuery 上执行，
     * 需要公开方法供外部调用。
     */
    public function triggerAfterRead(): void
    {
        $this->triggerEvent('afterRead');
    }

    /**
     * 获取已注册的事件名（调试/测试用）
     *
     * @return array{global: list<string>, model: list<string>}
     */
    public static function getRegisteredEvents(): array
    {
        return [
            'global' => array_keys(self::$globalListeners),
            'model' => array_keys(self::$modelListeners[static::class] ?? []),
        ];
    }

    /**
     * 清理监听器（Swoole Worker 重置）
     */
    public static function resetEvents(): void
    {
        self::$globalListeners = [];
        self::$modelListeners[static::class] = [];
    }
}
