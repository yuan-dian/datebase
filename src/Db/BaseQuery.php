<?php

declare(strict_types=1);

namespace yuandian\Database\Db;

use yuandian\Database\Db\Concern\AggregateQuery;
use yuandian\Database\Db\Concern\ParamsBind;
use yuandian\Database\Db\Concern\WhereQuery;
use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

/**
 * 查询基类
 * @template TModel of Model
 */
abstract class BaseQuery
{
    use WhereQuery;
    use AggregateQuery;
    use ParamsBind;

    /** @var class-string<TModel> */
    protected string $modelClass;

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
        'with'   => [],
        'lock'   => false,
        'union'  => [],
        'force'  => false,
    ];

    protected array $bind = [];

    protected bool $withoutScopes = false;
    protected array $removedScopes = [];

    /**
     * @param class-string<TModel> $modelClass
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->options['table'] = $this->modelClass::getTableName();
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

    // ======================== 抽象方法 ========================

    /**
     * 创建同连接的子查询实例（用于 Closure 嵌套条件）
     */
    abstract protected function newSubQuery(): static;

    // ------- 终端方法 -------

    /**
     * @return TModel|string|null
     */
    abstract public function find(): Model|string|null;

    /**
     * @return TModel[]|string
     */
    abstract public function select(): array|string;

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
            $this->where($query);
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
        $this->options['data'][$field] = new Express('+', $step);
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
        $this->options['data'][$field] = new Express('-', $step);
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
        return $this->inc($field, $step)->update();
    }

    /**
     * 字段值减少并更新
     *
     * @param string $field 字段名
     * @param float|int $step 减少值
     */
    public function setDec(string $field, float|int $step = 1): int
    {
        return $this->dec($field, $step)->update();
    }

    /**
     * 获取执行的SQL语句而不进行实际的查询
     *
     * @param bool $fetch 是否返回sql
     */
    public function fetchSql(bool $fetch = true): static
    {
        $this->options['fetch_sql'] = $fetch;
        return $this;
    }

    /**
     * 创建子查询SQL
     *
     * @param bool $sub 是否添加括号
     */
    public function buildSql(bool $sub = true): string
    {
        $this->options['fetch_sql'] = true;
        $result = $this->select();

        if (is_string($result)) {
            return $sub ? '( ' . $result . ' )' : $result;
        }

        return '';
    }

    // ======================== 链式方法 — 排序 / 分页 ========================

    public function order(string $field, string $direction = 'asc'): static
    {
        $this->options['order'][] = [$field, $direction];
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

    public function field(string|array $fields): static
    {
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }
        $this->options['field'] = $fields;
        return $this;
    }

    public function table(string $table): static
    {
        $this->options['table'] = $table;
        return $this;
    }

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

    // ======================== 链式方法 — 关联预加载 ========================

    public function with(string ...$relations): static
    {
        $this->options['with'] = array_merge($this->options['with'], $relations);
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
        $this->options['group'] = array_merge($this->options['group'], $fields);
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

    /**
     * 行数据 → 模型实例（基础实现）
     *
     * MongoDB 子类可覆写处理 ObjectId 等 BSON 类型
     */
    /**
     * @return TModel|Model|null
     * @date 2026/5/7 上午11:07
     * @author 原点 467490186@qq.com
     */
    protected function toModel(array $row): Model
    {
        /** @var TModel $model */
        $model = new $this->modelClass();
        $model->setExists(true);

        $columnMap = $model::getColumnMap();
        $reverseMap = array_flip($columnMap);
        $jsonColumns = $model::getJsonColumns();

        foreach ($row as $column => $value) {
            $propName = $reverseMap[$column] ?? StrUtil::camel($column);
            if (property_exists($model, $propName)) {
                // JSON 列：先反序列化再赋值
                if (array_key_exists($propName, $jsonColumns)) {
                    $value = $model::castFromJson($value, $jsonColumns[$propName]);
                }

                // NULL 跳过赋值：保留属性默认值，避免向非可空属性塞 null（TypePHP 类型不可变）
                if ($value !== null) {
                    $model->$propName = $value;
                }
            }
        }

        return $model;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
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
     * 忽略全局作用域
     */
    public function force(): static
    {
        $this->options['force'] = true;
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

        // 4. 设置分页参数
        $this->options['limit'] = $listRows;
        $this->options['offset'] = ($currentPage - 1) * $listRows;

        // 5. 查询当前页数据
        $items = $this->select();

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
        // 1. 多查一条用于判断是否有下一页
        $this->options['limit'] = $listRows + 1;
        $this->options['offset'] = ($currentPage - 1) * $listRows;

        // 2. 查询数据
        $items = $this->select();

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
