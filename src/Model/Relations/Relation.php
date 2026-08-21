<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Relations;

use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Model\Model;
use yuandian\Database\Model\ModelQuery;
use yuandian\Database\Model\MongoModelQuery;
use yuandian\Tools\utils\StrUtil;

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
     * 从模型数组中收集本地键值（用于批量 IN 查询）
     * @param Model[] $models
     * @return list<scalar>
     */
    abstract protected function collectLocalValues(array $models): array;

    /**
     * 从 matchMany 分组结果中提取单个模型的关联值
     * HasOne/HasOneThrough: $group[$key] ?? null（取首条）
     * HasMany/HasManyThrough: $group[$key] ?? []（取整组）
     */
    abstract protected function extractResult(mixed $group, mixed $key): mixed;

    /**
     * 批量预加载：收集 localKey → matchMany → 分发回模型
     * @param Model[] $models
     * @return Model[] 加载出的关联模型实例
     */
    public function eagerLoad(array $models, string $name): array
    {
        $localKeyProp = StrUtil::camel($this->localKey);
        $values = $this->collectLocalValues($models);
        if (empty($values)) {
            foreach ($models as $model) {
                $model->setRelation($name, $this->extractResult(null, null));
            }
            return [];
        }
        $map = $this->matchMany($values);
        $instances = [];
        foreach ($models as $model) {
            $keyVal = $model->{$localKeyProp} ?? null;
            $result = $this->extractResult($map[$keyVal] ?? null, $keyVal);
            $model->setRelation($name, $result);
            if (is_array($result)) {
                $instances = array_merge($instances, $result);
            } elseif ($result !== null) {
                $instances[] = $result;
            }
        }
        return $instances;
    }

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
