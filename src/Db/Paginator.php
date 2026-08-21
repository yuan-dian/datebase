<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use yuandian\Database\Model\Model;

/**
 * 分页器
 *
 * 支持完整分页和简单分页两种模式：
 *   - 完整分页：查询总数，显示所有页码
 *   - 简单分页：不查总数，仅显示上一页/下一页
 *
 * 实现 JsonSerializable / ArrayAccess / Countable / IteratorAggregate，
 * 可直接 json_encode / foreach / count / 数组下标访问。
 *
 * @template TItem 当前页元素类型（Db 层为数组，Model 层为模型实例）
 */
class Paginator implements JsonSerializable, ArrayAccess, Countable, IteratorAggregate
{
    protected static ?Closure $currentPageResolver = null;

    /**
     * 自定义分页类
     */
    protected static ?Closure $maker = null;

    /**
     * @param TItem[] $items 当前页数据
     * @param int $total 总记录数（简单模式为 0）
     * @param int $pageSize 每页条数
     * @param int $currentPage 当前页码
     * @param bool $simple 是否简单模式
     * @param bool $hasMore 简单模式：是否有下一页
     */
    public function __construct(
        protected array $items,
        protected int $total,
        protected int $pageSize,
        protected int $currentPage,
        protected bool $simple = false,
        protected bool $hasMore = false,
    ) {
    }

    public static function make(
        array $items,
        int $total,
        int $pageSize,
        int $currentPage,
        bool $simple = false,
        bool $hasMore = false,
    ) {
        if (isset(static::$maker)) {
            return call_user_func(static::$maker, $items, $total, $pageSize, $currentPage, $simple, $hasMore);
        }

        return new static($items, $total, $pageSize, $currentPage, $simple, $hasMore);
    }

    public static function maker(Closure $resolver): void
    {
        static::$maker = $resolver;
    }

    // ======================== 数据访问 ========================

    /**
     * 当前页数据
     *
     * @return TItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * 总记录数
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * 每页条数
     */
    public function perPage(): int
    {
        return $this->pageSize;
    }

    /**
     * 当前页码
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * 最后一页页码（简单模式始终返回当前页）
     */
    public function lastPage(): int
    {
        if ($this->simple) {
            return $this->currentPage;
        }

        return max(1, (int)ceil($this->total / $this->pageSize));
    }

    // ======================== 状态判断 ========================

    /**
     * 是否有多页（超过一页）
     */
    public function hasPages(): bool
    {
        if ($this->simple) {
            return $this->currentPage > 1 || $this->hasMore;
        }

        return $this->lastPage() > 1;
    }

    /**
     * 是否有下一页
     */
    public function hasMorePages(): bool
    {
        if ($this->simple) {
            return $this->hasMore;
        }

        return $this->currentPage < $this->lastPage();
    }

    /**
     * 是否在第一页
     */
    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    /**
     * 是否在最后一页
     */
    public function onLastPage(): bool
    {
        if ($this->simple) {
            return !$this->hasMore;
        }

        return $this->currentPage >= $this->lastPage();
    }

    /**
     * 自动获取当前页码
     *
     * @param string $varPage
     * @param int $default
     *
     * @return int
     */
    public static function getCurrentPage(string $varPage = 'page', int $default = 1): int
    {
        if (isset(static::$currentPageResolver)) {
            return call_user_func(static::$currentPageResolver, $varPage);
        }

        return $default;
    }

    /**
     * 设置获取当前页码闭包.
     *
     * @param Closure $resolver
     */
    public static function currentPageResolver(Closure $resolver): void
    {
        static::$currentPageResolver = $resolver;
    }

    /**
     * 重置静态闭包（Swoole/RoadRunner 等常驻进程 Worker 启动时调用）
     */
    public static function reset(): void
    {
        static::$currentPageResolver = null;
        static::$maker = null;
    }

    // ======================== 序列化 ========================

    /**
     * 转为数组
     */
    public function toArray(): array
    {
        return [
            'total'       => $this->total,
            'pageSize'    => $this->pageSize,
            'currentPage' => $this->currentPage,
            'lastPage'    => $this->lastPage(),
            'hasMore'     => $this->hasMorePages(),
            'items'       => array_map(function ($item) {
                if ($item instanceof Model) {
                    return $item->toArray();
                }
                return $item;
            }, $this->items),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->jsonSerialize(), JSON_UNESCAPED_UNICODE);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ======================== Countable ========================

    public function count(): int
    {
        return count($this->items);
    }

    // ======================== IteratorAggregate ========================

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    // ======================== ArrayAccess ========================

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }


}
