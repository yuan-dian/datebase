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
     * 批量加载：按 localKey 值一次性查询关联并分组。
     * 返回 [localKeyValue => Model|array|null]（HasMany 系为 list<Model>）。
     *
     * @param array $localValues localKey 值集合（去重后）
     * @return array<mixed, Model|array|null>
     */
    abstract public function matchMany(array $localValues): array;

    /**
     * 创建关联查询的 Query 实例
     *
     * @return ModelQuery|MongoModelQuery
     */
    public function newQuery(): BaseQuery
    {
        return $this->newQueryFor($this->related);
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
