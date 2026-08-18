<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Model\Model;
use yuandian\Database\Model\ModelQuery;
use yuandian\Database\Model\MongoModelQuery;

abstract class Relation
{
    protected Model $parent;

    /** @var class-string<Model> $related */
    protected string $related;
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $parent, string $related, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function getRelated(): string
    {
        return $this->related;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    abstract public function getResults(): Model|array|null;

    /**
     * 创建关联查询的 Query 实例
     *
     * @return ModelQuery|MongoModelQuery
     */
    public function newQuery(): BaseQuery
    {
        return Model::newQueryForClass($this->related);
    }

    /**
     * 为指定模型类创建 Query 实例（用于中间表等非直接关联模型）
     *
     * @param class-string<Model> $modelClass
     * @return ModelQuery|MongoModelQuery
     */
    public function newQueryFor(string $modelClass): BaseQuery
    {
        return Model::newQueryForClass($modelClass);
    }
}
