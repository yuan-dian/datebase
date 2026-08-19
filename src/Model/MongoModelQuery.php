<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Db\Connector\Mongo as MongoConnection;
use yuandian\Database\Db\MongoQuery;
use yuandian\Database\Model\Concern\EagerLoadRelations;
use yuandian\Database\Model\Concern\HydratesModels;

/**
 * MongoDB 模型查询：Mongo 查询器的模型包装，负责 ObjectID 转换、软删除与关联预加载。
 *
 * @template TModel of Model
 * @extends BaseQuery<TModel>
 * @mixin \yuandian\Database\Db\Concern\WhereQuery
 * @mixin \yuandian\Database\Db\Concern\AggregateQuery
 * @date 2026/5/8 上午10:54
 * @author 原点 467490186@qq.com
 */
class MongoModelQuery extends MongoQuery
{
    use HydratesModels;
    use EagerLoadRelations;

    /** @var class-string<TModel> */
    protected string $modelClass;

    public function __construct(MongoConnection $connection, string $modelClass)
    {
        parent::__construct($connection, $modelClass::getTableName());

        $this->modelClass = $modelClass;
    }

    protected function newSubQuery(): static
    {
        return new static($this->connection, $this->modelClass);
    }

    /**
     * chunk 分块时同步预加载名（withRelations 独立于 options，需经钩子拷贝）
     */
    protected function copyExtraState(BaseQuery $query): void
    {
        /** @var MongoModelQuery $query chunk 的 newSubQuery 返回 static，运行时必为 MongoModelQuery */
        if (!empty($this->withRelations)) {
            $query->withRelations = $this->withRelations;
        }
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Mongo 模型查询字段名转换：camelCase 属性 → snake_case 列名。
     *
     * 存储侧（Model::getFields）已将 camelCase 属性转为 snake_case 存入文档；
     * 查询侧必须同规则转换，否则 where('parentId') 查 'parentId' 字段而文档存
     * 'parent_id'，关联查询（whereIn 批量）与条件查询将全部落空。
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
    public function find(): Model|null
    {
        $row = parent::find();

        if ($row === null) {
            return null;
        }

        $model = $this->hydrateMongo($row);

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
        $models = [];
        foreach (parent::select() as $row) {
            $models[] = $this->hydrateMongo($row);
        }

        if (!empty($this->withRelations) && !empty($models)) {
            $this->eagerLoadRelations($models, $this->withRelations);
        }

        return $models;
    }

    protected function hydrateMongo(array $row): Model
    {
        foreach ($row as $column => $value) {
            if ($value instanceof \MongoDB\BSON\ObjectID) {
                $row[$column] = (string)$value;
            }
        }

        return $this->hydrate($row);
    }
}