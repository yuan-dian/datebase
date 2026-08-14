<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Model;

abstract class Relation
{
    protected Model $parent;

    /** @var class-string<Model> $related */
    protected string $related;
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $parent, string $related, ?string $foreignKey = null, ?string $localKey = null)
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
     */
    public function newQuery(): BaseQuery
    {
        return $this->newQueryFor($this->related);
    }

    /**
     * 为指定模型类创建 Query 实例（用于中间表等非直接关联模型）
     *
     * @param class-string<Model> $modelClass
     */
    public function newQueryFor(string $modelClass): BaseQuery
    {
        $connName = $modelClass::getConnectionName();

        $connection = Db::connect($connName);

        return $connection->newQuery($modelClass);
    }
}
