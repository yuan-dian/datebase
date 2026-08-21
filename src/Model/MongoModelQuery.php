<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Db\Connector\Mongo as MongoConnection;
use yuandian\Database\Db\MongoQuery;
use yuandian\Database\Model\Concern\EagerLoadRelations;
use yuandian\Database\Model\Concern\HasSoftDeleteQuery;
use yuandian\Database\Model\Concern\ConvertsToModels;
use yuandian\Database\Model\Concern\ModelQueryShared;

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
    use ConvertsToModels;
    use EagerLoadRelations;
    use HasSoftDeleteQuery;
    use ModelQueryShared;

    public function __construct(MongoConnection $connection, string $modelClass)
    {
        parent::__construct($connection, $modelClass::getTableName());

        $this->modelClass = $modelClass;
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

        return $this->toModel($row);
    }
}
