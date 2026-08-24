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

    /** 是否为"多"关联（HasMany/HasManyThrough/BelongsToMany），影响 extractResult 默认行为 */
    protected bool $isMany = false;

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
     * 按指定列名收集模型数组的值（去重，忽略 null）
     *
     * @param Model[] $models
     * @param string $column 数据库列名（snake_case）
     * @return list<scalar>
     */
    protected function collectValuesByColumn(array $models, string $column): array
    {
        $prop = StrUtil::camel($column);
        $values = [];
        foreach ($models as $model) {
            $value = $model->{$prop} ?? null;
            if ($value !== null) {
                $values[] = $value;
            }
        }
        return array_values(array_unique($values));
    }

    /**
     * 从模型数组中收集本地键值（用于批量 IN 查询）
     * 默认使用 localKey；BelongsTo 子类覆写为 foreignKey
     *
     * @param Model[] $models
     * @return list<scalar>
     */
    protected function collectLocalValues(array $models): array
    {
        return $this->collectValuesByColumn($models, $this->localKey);
    }

    /**
     * 从 matchMany 分组结果中提取单个模型的关联值
     * One 关联返回 Model|null，Many 关联返回 Model[]
     */
    protected function extractResult(mixed $group, mixed $key): mixed
    {
        return $this->isMany ? ($group ?? []) : ($group ?? null);
    }

    /**
     * eagerLoad 时用于从模型上读取分组 key 的属性名
     * 默认使用 localKey（HasOne/HasMany/Through 系列）
     * BelongsTo 子类覆写为 foreignKey
     */
    protected function getLookupKeyProperty(): string
    {
        return StrUtil::camel($this->localKey);
    }

    /**
     * 批量预加载：收集 localKey → matchMany → 分发回模型
     * @param Model[] $models
     * @return Model[] 加载出的关联模型实例
     */
    public function eagerLoad(array $models, string $name): array
    {
        $lookupKey = $this->getLookupKeyProperty();
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
            $keyVal = $model->{$lookupKey} ?? null;
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
