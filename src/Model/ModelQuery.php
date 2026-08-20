<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Db\Connection;
use yuandian\Database\Db\Query;
use yuandian\Database\Model\Concern\EagerLoadRelations;
use yuandian\Database\Model\Concern\HasSoftDeleteQuery;
use yuandian\Database\Model\Concern\HydratesModels;

/**
 * 模型查询：Db 层查询器的模型包装，负责水合、软删除、全局作用域与关联预加载。
 *
 * @template TModel of Model
 * @extends BaseQuery<TModel>
 * @mixin \yuandian\Database\Db\Concern\WhereQuery
 * @mixin \yuandian\Database\Db\Concern\AggregateQuery
 * @date 2026/5/7 上午11:07
 * @author 原点 467490186@qq.com
 */
class ModelQuery extends Query
{
    use HydratesModels;
    use EagerLoadRelations;
    use HasSoftDeleteQuery;

    /** @var class-string<TModel> */
    protected string $modelClass;

    /**
     * @param Connection $connection
     * @param class-string<TModel> $modelClass
     */
    public function __construct(Connection $connection, string $modelClass)
    {
        parent::__construct($connection, $modelClass::getTableName());

        $this->modelClass = $modelClass;
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    protected function newSubQuery(): static
    {
        return new static($this->connection, $this->modelClass);
    }

    /**
     * chunk 分块时同步查询状态与预加载名（withRelations 独立于 state，需经钩子拷贝）
     */
    protected function copyExtraState(BaseQuery $query): void
    {
        /** @var ModelQuery $query chunk 的 newSubQuery 返回 static，运行时必为 ModelQuery */
        $query->state = $this->state->copy();
        if (!empty($this->withRelations)) {
            $query->withRelations = $this->withRelations;
        }
    }

    /**
     * 字段名转换：属性名（camelCase）→ 列名（snake_case），基于元数据反查。
     *
     * 仅转换已声明的属性名；非属性名（如真实列名、SQL 片段）原样返回。
     * 限定名 table.column 只转换列段。
     */
    protected function convertFieldName(string $field): string
    {
        if ($field === '') {
            return $field;
        }

        $dot = strrpos($field, '.');
        if ($dot !== false) {
            $table = substr($field, 0, $dot);
            $column = substr($field, $dot + 1);
            $columnMap = $this->modelClass::getColumnMap();

            return $table . '.' . ($columnMap[$column] ?? $column);
        }

        $columnMap = $this->modelClass::getColumnMap();

        return $columnMap[$field] ?? $field;
    }

    /**
     * @return TModel|null
     */
    public function find(): ?Model
    {
        $this->applyGlobalScopes();

        $result = parent::find();

        if ($result === null) {
            return null;
        }

        $model = $this->hydrate($result);

        if (!empty($this->withRelations)) {
            $this->eagerLoadRelations([$model], $this->withRelations);
        }

        return $model;
    }

    /**
     * @return TModel[]
     */
    public function select(): array
    {
        $this->applyGlobalScopes();

        $rows = parent::select();

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrate($row);
        }

        if (!empty($this->withRelations) && !empty($models)) {
            $this->eagerLoadRelations($models, $this->withRelations);
        }

        return $models;
    }

    /**
     * @return \Generator<int, TModel>
     */
    public function cursor(): \Generator
    {
        foreach (parent::cursor() as $row) {
            yield $this->hydrate($row);
        }
    }

    public function update(array $data): int
    {
        $this->applyGlobalScopes();

        return parent::update($data);
    }
}