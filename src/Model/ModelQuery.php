<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Db\Connection;
use yuandian\Database\Db\Query;
use yuandian\Database\Model\Concern\EagerLoadRelations;
use yuandian\Database\Model\Concern\HasSoftDeleteQuery;
use yuandian\Database\Model\Concern\HydratesModels;
use yuandian\Database\Model\Concern\ModelQueryShared;

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
    use ModelQueryShared;

    /**
     * @param Connection $connection
     * @param class-string<TModel> $modelClass
     */
    public function __construct(Connection $connection, string $modelClass)
    {
        parent::__construct($connection, $modelClass::getTableName());

        $this->modelClass = $modelClass;
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