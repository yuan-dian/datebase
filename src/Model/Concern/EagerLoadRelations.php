<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Model\Model;
use yuandian\Database\Model\Relations\HasManyRelation;
use yuandian\Database\Model\Relations\HasManyThroughRelation;
use yuandian\Database\Model\Relations\HasOneRelation;
use yuandian\Database\Model\Relations\HasOneThroughRelation;
use yuandian\Database\Model\Relations\Relation;
use yuandian\Tools\utils\StrUtil;

/**
 * 关联预加载能力：以 WHERE IN 批量查询替代 N+1 循环查询。
 *
 * 使用该 trait 的宿主类必须声明 protected string $modelClass 属性（模型类名）。
 *
 * @date 2026/5/8 上午10:54
 * @author 原点 467490186@qq.com
 */
trait EagerLoadRelations
{
    public function with(string ...$relations): static
    {
        $this->options['with'] = array_merge($this->options['with'], $relations);
        return $this;
    }

    /**
     * 批量预加载关联（消除 N+1 查询）
     *
     * 按关系类型分组收集所有模型的 localKey 值，一次 WHERE IN 查出全部关联模型，
     * 再按 foreignKey 值分组分配回各模型。
     *
     * @param Model[] $models
     * @param string[] $relations
     */
    public function eagerLoadRelations(array $models, array $relations): void
    {
        if (empty($models) || empty($relations)) {
            return;
        }

        $first = $models[0];

        foreach ($relations as $name) {
            $info = $first::getRelationInfo($name);
            if (!$info) {
                continue;
            }

            /** @var HasOne|HasMany|HasOneThrough|HasManyThrough $attr */
            $attr = $info['attribute'];

            switch ($info['type']) {
                case 'HasOne':
                    $this->eagerLoadHasOne($models, $name, $attr);
                    break;
                case 'HasMany':
                    $this->eagerLoadHasMany($models, $name, $attr);
                    break;
                case 'HasOneThrough':
                    $this->eagerLoadHasOneThrough($models, $name, $attr);
                    break;
                case 'HasManyThrough':
                    $this->eagerLoadHasManyThrough($models, $name, $attr);
                    break;
            }
        }
    }

    /**
     * 批量加载 HasOne：按 localKey 值分组，每组取第一条
     *
     * @param Model[] $models
     */
    protected function eagerLoadHasOne(array $models, string $name, HasOne $attr): void
    {
        $relation = new HasOneRelation($models[0], $attr->model, $attr->foreignKey, $attr->localKey);

        $grouped = $this->batchLoadGrouped($models, $relation, $name);

        $localKeyProp = StrUtil::camel($relation->getLocalKey());
        foreach ($models as $model) {
            $localValue = $model->$localKeyProp ?? null;
            $model->setRelation($name, $grouped[$localValue][0] ?? null);
        }
    }

    /**
     * 批量加载 HasMany：按 localKey 值分组，整组赋值
     *
     * @param Model[] $models
     */
    protected function eagerLoadHasMany(array $models, string $name, HasMany $attr): void
    {
        $relation = new HasManyRelation($models[0], $attr->model, $attr->foreignKey, $attr->localKey);

        $grouped = $this->batchLoadGrouped($models, $relation, $name);

        $localKeyProp = StrUtil::camel($relation->getLocalKey());
        foreach ($models as $model) {
            $localValue = $model->$localKeyProp ?? null;
            $model->setRelation($name, $grouped[$localValue] ?? []);
        }
    }

    /**
     * 批量加载 HasOneThrough：两段查询后按 throughPk 关联，每组取第一条
     *
     * @param Model[] $models
     */
    protected function eagerLoadHasOneThrough(array $models, string $name, HasOneThrough $attr): void
    {
        $grouped = $this->batchLoadThroughGrouped($models, $name, $attr);

        $localKeyProp = StrUtil::camel($attr->localKey ?: $models[0]::getPkColumn());
        foreach ($models as $model) {
            $localValue = $model->$localKeyProp ?? null;
            $model->setRelation($name, $grouped[$localValue][0] ?? null);
        }
    }

    /**
     * 批量加载 HasManyThrough：两段查询后按 throughPk 关联，整组赋值
     *
     * @param Model[] $models
     */
    protected function eagerLoadHasManyThrough(array $models, string $name, HasManyThrough $attr): void
    {
        $grouped = $this->batchLoadThroughGrouped($models, $name, $attr);

        $localKeyProp = StrUtil::camel($attr->localKey ?: $models[0]::getPkColumn());
        foreach ($models as $model) {
            $localValue = $model->$localKeyProp ?? null;
            $model->setRelation($name, $grouped[$localValue] ?? []);
        }
    }

    /**
     * 直接关系批量查询：收集所有模型的 localKey 值 → 一次 WHERE IN → 按 foreignKey 分组
     *
     * @param Model[] $models
     * @return array<mixed, Model[]>
     */
    protected function batchLoadGrouped(array $models, Relation $relation, string $name): array
    {
        $localKey = $relation->getLocalKey();
        $foreignKey = $relation->getForeignKey();

        $localKeyProp = StrUtil::camel($localKey);
        $foreignKeyProp = StrUtil::camel($foreignKey);

        // 收集所有模型的 localKey 值
        $localValues = [];
        foreach ($models as $model) {
            $value = $model->$localKeyProp ?? null;
            if ($value !== null) {
                $localValues[] = $value;
            }
        }
        $localValues = array_values(array_unique($localValues));

        if (empty($localValues)) {
            return [];
        }

        // 一次查询全部关联模型
        $relatedModels = $relation->newQuery()
            ->whereIn($foreignKey, $localValues)
            ->select();

        // 按 foreignKey 值分组
        $grouped = [];
        foreach ($relatedModels as $related) {
            $grouped[$related->$foreignKeyProp][] = $related;
        }

        return $grouped;
    }

    /**
     * 多级关系批量查询：查中间表 → 收集 throughPk 值 → 一次 WHERE IN 查最终关联 → 按 throughPk 分组
     *
     * @param Model[] $models
     * @return array<mixed, Model[]>
     */
    protected function batchLoadThroughGrouped(array $models, string $name, HasOneThrough|HasManyThrough $attr): array
    {
        $parent = $models[0];
        $relation = match (true) {
            $attr instanceof HasOneThrough => new HasOneThroughRelation(
                $parent, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ),
            default => new HasManyThroughRelation(
                $parent, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ),
        };

        $localKey = $relation->getLocalKey();
        $foreignKey = $relation->getForeignKey();
        $throughKey = $relation->getThroughKey();

        $localKeyProp = StrUtil::camel($localKey);
        $foreignKeyProp = StrUtil::camel($foreignKey);
        $throughKeyProp = StrUtil::camel($throughKey);

        // Step 1: 收集父模型 localKey 值
        $localValues = [];
        foreach ($models as $model) {
            $value = $model->$localKeyProp ?? null;
            if ($value !== null) {
                $localValues[] = $value;
            }
        }
        $localValues = array_values(array_unique($localValues));

        if (empty($localValues)) {
            return [];
        }

        // Step 2: 查中间表
        $throughQuery = $relation->newQueryFor($relation->getThrough());
        $throughModels = $throughQuery
            ->whereIn($foreignKey, $localValues)
            ->select();

        // Step 3: 收集中间表中指向目标表的列值（throughKey 列的值 = 目标表主键值）
        $throughKeyVals = [];
        foreach ($throughModels as $throughModel) {
            $throughKeyVals[] = $throughModel->$throughKeyProp;
        }
        $throughKeyVals = array_values(array_unique($throughKeyVals));

        if (empty($throughKeyVals)) {
            return [];
        }

        // Step 4: 一次查询最终关联（在目标表按主键 IN）
        $relatedClass = $relation->getRelated();
        $finalModels = $relation->newQuery()
            ->whereIn($relatedClass::getPkColumn(), $throughKeyVals)
            ->select();

        // Step 5: 按目标表主键值分组
        $byPk = [];
        $relatedPkProp = StrUtil::camel($relatedClass::getPkColumn());
        foreach ($finalModels as $final) {
            $byPk[$final->$relatedPkProp][] = $final;
        }

        // Step 6: 中间表按 foreignKey 分组，把 throughKey 值 → 最终关联映射到每个父模型
        $grouped = [];
        foreach ($throughModels as $throughModel) {
            $fkValue = $throughModel->$foreignKeyProp;
            $tkValue = $throughModel->$throughKeyProp;
            foreach ($byPk[$tkValue] ?? [] as $final) {
                $grouped[$fkValue][] = $final;
            }
        }

        return $grouped;
    }
}
