<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Db\Connection;
use yuandian\Database\Db\Query;
use yuandian\Database\Model\Concern\EagerLoadRelations;
use yuandian\Database\Model\Concern\HydratesModels;

/**
 * 模型查询：Db 层查询器的模型包装，负责水合、软删除、全局作用域与关联预加载。
 *
 * @template TModel of Model
 * @extends Query
 * @mixin \yuandian\Database\Db\Concern\WhereQuery
 * @mixin \yuandian\Database\Db\Concern\AggregateQuery
 * @date 2026/5/7 上午11:07
 * @author 原点 467490186@qq.com
 */
class ModelQuery extends Query
{
    use HydratesModels;
    use EagerLoadRelations;

    /** @var class-string<TModel> */
    protected string $modelClass;

    /** @var bool 全局作用域是否已应用 */
    protected bool $scopesApplied = false;

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
     * @return Model|string|null
     */
    public function find(): Model|string|null
    {
        $this->applyGlobalScopes();

        $result = parent::find();

        if (is_string($result) || $result === null) {
            return $result;
        }

        $model = $this->hydrate($result);

        if (!empty($this->options['with'])) {
            $model->load(...$this->options['with']);
        }

        return $model;
    }

    /**
     * @return TModel[]|string
     */
    public function select(): array|string
    {
        $this->applyGlobalScopes();

        $rows = parent::select();

        if (is_string($rows)) {
            return $rows;
        }

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrate($row);
        }

        if (!empty($this->options['with']) && !empty($models)) {
            $this->eagerLoadRelations($models, $this->options['with']);
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

    public function delete(): int
    {
        $this->applyGlobalScopes();

        // force() / withoutGlobalScopes() 时跳过软删除，执行物理删除
        $force = !empty($this->options['force']) || $this->withoutScopes;

        $softDelete = $this->modelClass::getSoftDelete();
        if (!$force && $softDelete && $softDelete->enabled) {
            return $this->update([$softDelete->column => date('Y-m-d H:i:s')]);
        }

        return parent::delete();
    }

    protected function applyGlobalScopes(): void
    {
        if ($this->withoutScopes || $this->scopesApplied) {
            return;
        }

        $this->scopesApplied = true;
        $softDelete = $this->modelClass::getSoftDelete();
        if ($softDelete && $softDelete->enabled) {
            $this->whereNull($softDelete->column);
        }
    }
}