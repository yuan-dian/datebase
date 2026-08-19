<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\Db\Concern\AggregateQuery;
use yuandian\Database\Db\Concern\ParamsBind;
use yuandian\Database\Db\Concern\WhereQuery;
use yuandian\Database\Exceptions\DbException;

/**
 * 查询基类
 *
 * @template TModel 行数据类型（Db 层为 array<string, mixed>；Model 层为具体模型类）
 */
abstract class BaseQuery
{
    use WhereQuery;
    use AggregateQuery;
    use ParamsBind;

    protected array $options = [
        'table'  => '',
        'alias'  => '',
        'field'  => ['*'],
        'where'  => [],
        'order'  => [],
        'limit'  => null,
        'offset' => null,
        'join'   => [],
        'group'  => [],
        'having' => [],
        'lock'   => false,
        'union'       => [],
        'force_delete' => false,
    ];

    protected array $bind = [];

    protected bool $withoutScopes = false;
    protected array $removedScopes = [];

    /**
     * @param string|null $table 数据表名；null 时需后续调用 table() 指定
     */
    public function __construct(?string $table = null)
    {
        if ($table !== null) {
            $this->options['table'] = $table;
        }
    }

    /**
     * 确保已指定数据表
     */
    protected function ensureTable(): void
    {
        if (empty($this->options['table'])) {
            throw new DbException('查询未指定数据表，请先调用 table()');
        }
    }

    /**
     * 获取查询参数.
     *
     * @param string $name 参数名
     * @param mixed $default 默认值
     *
     * @return mixed
     */
    public function getOption(string $name, ?string $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * 设置查询选项
     *
     * @param string $name 参数名
     * @param mixed $value 参数值
     */
    public function setOption(string $name, mixed $value): void
    {
        $this->options[$name] = $value;
    }

    /**
     * 移除查询选项
     *
     * @param string $name 参数名
     */
    public function removeOption(string $name): void
    {
        unset($this->options[$name]);
    }

    // ======================== 抽象方法 ========================

    /**
     * 创建同连接的子查询实例（用于 Closure 嵌套条件）
     */
    abstract protected function newSubQuery(): static;

    // ------- 终端方法 -------
    // 返回类型用公共上界 array|object|null / array：
    // Db 层子类收窄为 ?array（Query/MongoQuery），Model 层收窄为 ?Model（ModelQuery/MongoModelQuery）。
    // PHP 协变规则要求父类返回类型是子类的父型，直接声明 array 会锁死 Model 层的收窄。

    /**
     * 查询单条记录
     *
     * @return array<string, mixed>|object|null 行数据（Db 层为数组，Model 层为模型实例）
     */
    abstract public function find(): array|object|null;

    /**
     * 查询多条记录
     *
     * @return list<array<string, mixed>>|list<object> 行数据数组（Db 层为数组行，Model 层为模型列表）
     */
    abstract public function select(): array;

    /**
     * @return int|string  SQL 返回自增 ID（int），MongoDB 返回 ObjectId（string）
     */
    abstract public function insert(array $data): int|string;

    abstract public function insertAll(array $dataList): int;

    abstract public function update(array $data): int;

    abstract public function delete(): int;

    // ======================== 链式方法 — 条件查询（通过 trait） ========================

    /**
     * 条件查询
     *
     * @param mixed $condition 满足条件（支持闭包）
     * @param \Closure|array $query 满足条件后执行的查询表达式
     * @param \Closure|array|null $otherwise 不满足条件后执行
     */
    public function when(mixed $condition, \Closure|array $query, \Closure|array|null $otherwise = null): static
    {
        if ($condition instanceof \Closure) {
            $condition = $condition($this);
        }

        if ($condition) {
            $this->executeWhenQuery($query, $condition);
        } elseif ($otherwise) {
            $this->executeWhenQuery($otherwise, $condition);
        }

        return $this;
    }

    protected function executeWhenQuery(\Closure|array $query, mixed $condition): void
    {
        if ($query instanceof \Closure) {
            $query($this, $condition);
        } elseif (is_array($query)) {
            $this->whereMap($query);
        }
    }

    /**
     * 字段值增长
     *
     * @param string $field 字段名
     * @param float|int $step 增长值
     */
    public function inc(string $field, float|int $step = 1): static
    {
        $this->options['data'][$this->convertFieldName($field)] = new Express('+', $step);
        return $this;
    }

    /**
     * 字段值减少
     *
     * @param string $field 字段名
     * @param float|int $step 减少值
     */
    public function dec(string $field, float|int $step = 1): static
    {
        $this->options['data'][$this->convertFieldName($field)] = new Express('-', $step);
        return $this;
    }

    /**
     * 字段值增长并更新
     *
     * @param string $field 字段名
     * @param float|int $step 增长值
     */
    public function setInc(string $field, float|int $step = 1): int
    {
        $this->inc($field, $step);

        return $this->update($this->options['data'] ?? []);
    }

    /**
     * 字段值减少并更新
     *
     * @param string $field 字段名
     * @param float|int $step 减少值
     */
    public function setDec(string $field, float|int $step = 1): int
    {
        $this->dec($field, $step);

        return $this->update($this->options['data'] ?? []);
    }

    // ======================== 链式方法 — 排序 / 分页 ========================

    public function order(string $field, string $direction = 'asc'): static
    {
        $this->options['order'][] = [$this->convertFieldName($field), $direction];
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->options['limit'] = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->options['offset'] = $offset;
        return $this;
    }

    // ======================== 链式方法 — 字段 / 表 ========================

    /**
     * 查询字段（强制数组）
     *
     * 列表形式 field(['id', 'name'])；别名用键值对 field(['name' => 'user_name'])。
     *
     * @param array $fields 字段列表；元素可为字段名或 Raw；键值对表示别名（原名 => 别名）
     */
    public function field(array $fields): static
    {
        $converted = [];
        foreach ($fields as $key => $field) {
            if ($field instanceof Raw) {
                $converted[$key] = $field;
            } elseif (is_string($key)) {
                $converted[$this->convertFieldName($key)] = $field;
            } else {
                $converted[] = $this->convertFieldName($field);
            }
        }
        $this->options['field'] = $converted;
        return $this;
    }

    public function table(string $table): static
    {
        $this->options['table'] = $table;
        return $this;
    }

    /**
     * 指定查询表别名（FROM table AS alias，仅 select 生效）
     */
    public function alias(string $alias): static
    {
        $this->options['alias'] = $alias;
        return $this;
    }

    /**
     * 指定distinct查询
     */
    public function distinct(bool $distinct = true): static
    {
        $this->options['distinct'] = $distinct;
        return $this;
    }

    // ======================== 链式方法 — JOIN / GROUP / HAVING ========================

    public function join(string $table, string $condition, string $type = 'INNER'): static
    {
        $this->options['join'][] = compact('table', 'condition', 'type');
        return $this;
    }

    public function leftJoin(string $table, string $condition): static
    {
        return $this->join($table, $condition, 'LEFT');
    }

    public function rightJoin(string $table, string $condition): static
    {
        return $this->join($table, $condition, 'RIGHT');
    }

    public function groupBy(string ...$fields): static
    {
        $converted = [];
        foreach ($fields as $field) {
            $converted[] = $this->convertFieldName($field);
        }
        $this->options['group'] = array_merge($this->options['group'], $converted);
        return $this;
    }

    public function having(string $condition): static
    {
        $this->options['having'][] = $condition;
        return $this;
    }

    // ======================== 链式方法 — 作用域 ========================

    public function withoutGlobalScope(string $scope): static
    {
        $this->removedScopes[] = $scope;
        return $this;
    }

    public function withoutGlobalScopes(): static
    {
        $this->withoutScopes = true;
        return $this;
    }

    // ======================== 公共工具 ========================

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 悲观锁
     */
    public function lock(bool $lock = true): static
    {
        $this->options['lock'] = $lock;
        return $this;
    }

    /**
     * 联合查询
     */
    public function union(BaseQuery|array $query, string $type = 'UNION'): static
    {
        $this->options['union'][] = [
            'query' => $query,
            'type'  => $type,
        ];
        return $this;
    }

    /**
     * 分块处理
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;
        do {
            $query = $this->newSubQuery();
            $query->options = $this->options;
            $this->copyExtraState($query);
            $query->options['limit'] = $count;
            $query->options['offset'] = ($page - 1) * $count;
            $query->bind = $this->bind;

            $results = $query->select();
            if (empty($results)) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $page++;
        } while (count($results) === $count);

        return true;
    }

    /**
     * 子查询附加状态拷贝钩子：chunk 分块时除 options 外需同步的状态（模型层预加载名等）。
     * Db 层无附加状态，默认空实现；Model 层覆写补充。
     */
    protected function copyExtraState(BaseQuery $query): void
    {
    }

    /**
     * 强制物理删除（跳过软删除与全局作用域）
     *
     * 模型层专用：ModelQuery::delete() 读取该选项决定是否跳过软删除。
     * 查询级一步物理删除见 Query::forceDelete()（终端方法）。
     */
    public function force(): static
    {
        $this->options['force_delete'] = true;
        return $this;
    }

    /**
     * 指定查询强制使用索引（FORCE INDEX）
     *
     * SQL Builder 专用：生成 FORCE INDEX (col) 或 FORCE INDEX (col1, col2) 子句。
     *
     * @param string|array $index 索引名；多个索引传数组
     */
    public function forceIndex(string|array $index): static
    {
        $this->options['force_index'] = $index;
        return $this;
    }

    // ======================== 分页查询 ========================

    /**
     * 分页查询
     * @param int $listRows 每页条数
     * @param bool $simple 简单模式（不查总数，仅上一页/下一页）
     * @param int|null $page 页码页码
     * @param string $pageName 页码参数名（默认 'page'）
     * @return Paginator
     * @date 2026/5/8 上午10:54
     * @author 原点 467490186@qq.com
     */
    public function paginate(
        int $listRows = 15,
        bool $simple = false,
        ?int $page = null,
        string $pageName = 'page'
    ): Paginator {
        $currentPage = $this->getCurrentPage($page, $pageName);

        if ($simple) {
            return $this->paginateSimple($listRows, $currentPage);
        }

        return $this->paginateFull($listRows, $currentPage);
    }

    /**
     * 简单分页（不查总数）
     *
     * @param int $listRows 每页条数
     * @param int|null $page 页码页码
     * @param string $pageName 页码参数名（默认 'page'）
     * @return Paginator
     */
    public function simplePaginate(int $listRows = 15, ?int $page = null, string $pageName = 'page'): Paginator
    {
        return $this->paginate($listRows, true, $page, $pageName);
    }

    /**
     * 完整分页实现
     */
    protected function paginateFull(int $listRows, int $currentPage): Paginator
    {
        // 1. 查询总数
        $total = $this->count();

        // 2. 计算最后一页
        $lastPage = max(1, (int)ceil($total / $listRows));

        // 3. 确保当前页合法
        $currentPage = max(1, min($currentPage, $lastPage));

        // 4. 设置分页参数（保存原值，查询后恢复，避免污染查询对象）
        $prevLimit = $this->options['limit'] ?? null;
        $prevOffset = $this->options['offset'] ?? null;
        $this->options['limit'] = $listRows;
        $this->options['offset'] = ($currentPage - 1) * $listRows;

        // 5. 查询当前页数据
        try {
            $items = $this->select();
        } finally {
            if ($prevLimit === null) {
                unset($this->options['limit']);
            } else {
                $this->options['limit'] = $prevLimit;
            }
            if ($prevOffset === null) {
                unset($this->options['offset']);
            } else {
                $this->options['offset'] = $prevOffset;
            }
        }

        // 6. 构建分页器
        return Paginator::make(
            items: $items,
            total: $total,
            pageSize: $listRows,
            currentPage: $currentPage,
            hasMore: $lastPage > $currentPage,
        );
    }

    /**
     * 简单分页实现（多查一条判断是否有下一页，避免 COUNT 查询）
     */
    protected function paginateSimple(int $listRows, int $currentPage): Paginator
    {
        // 1. 多查一条用于判断是否有下一页（保存原值，查询后恢复，避免污染查询对象）
        $prevLimit = $this->options['limit'] ?? null;
        $prevOffset = $this->options['offset'] ?? null;
        $this->options['limit'] = $listRows + 1;
        $this->options['offset'] = ($currentPage - 1) * $listRows;

        // 2. 查询数据
        try {
            $items = $this->select();
        } finally {
            if ($prevLimit === null) {
                unset($this->options['limit']);
            } else {
                $this->options['limit'] = $prevLimit;
            }
            if ($prevOffset === null) {
                unset($this->options['offset']);
            } else {
                $this->options['offset'] = $prevOffset;
            }
        }

        // 3. 判断是否有下一页
        $hasMore = count($items) > $listRows;

        if ($hasMore) {
            array_pop($items);
        }

        // 4. 构建分页器
        return Paginator::make(
            items: $items,
            total: 0,
            pageSize: $listRows,
            currentPage: $currentPage,
            simple: true,
            hasMore: $hasMore,
        );
    }

    /**
     * 从请求中获取当前页码
     */
    protected function getCurrentPage(?int $page = null, $pageName = 'page'): int
    {
        $page = $page ?? Paginator::getCurrentPage($pageName);

        return max(1, $page);
    }
}
