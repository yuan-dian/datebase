<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Attribute\BelongsTo;
use yuandian\Database\Attribute\BelongsToMany;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Enums\RelationType;
use yuandian\Database\Model\Model;
use yuandian\Database\Model\Relations\BelongsToManyRelation;
use yuandian\Database\Model\Relations\BelongsToRelation;
use yuandian\Database\Model\Relations\HasManyRelation;
use yuandian\Database\Model\Relations\HasManyThroughRelation;
use yuandian\Database\Model\Relations\HasOneRelation;
use yuandian\Database\Model\Relations\HasOneThroughRelation;


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
    /** @var string[] 待预加载的关联名（模型层专属，不进入 Db 层 options） */
    protected array $withRelations = [];

    public function with(string ...$relations): static
    {
        $this->withRelations = array_merge($this->withRelations, $relations);
        return $this;
    }

    /**
     * 批量预加载关联（消除 N+1 查询）
     *
     * 按关系类型分组收集所有模型的 localKey 值，一次 WHERE IN 查出全部关联模型，
     * 再按 foreignKey 值分组分配回各模型。
     *
     * 支持点号嵌套：with('a.b') 先批量加载 a，再从 a 的批内实例递归批量加载 b，
     * 任意深度（a.b.c）均可；'a' 与 'a.b' 同时出现时 a 只加载一次。
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

        // 第一遍：拆分点号，收集平铺关系名（去重）与嵌套映射
        $flatNames = [];
        $nestedMap = [];
        foreach ($relations as $name) {
            if (str_contains($name, '.')) {
                [$parent, $nested] = explode('.', $name, 2);
                $flatNames[$parent] = true;
                $nestedMap[$parent][] = $nested;
            } else {
                $flatNames[$name] = true;
            }
        }
        // 第二遍：批量加载所有平铺关系，收集加载出的关联模型实例（供嵌套递归）
        $loadedInstances = [];
        foreach (array_keys($flatNames) as $name) {
            $info = $first::getRelationInfo($name);
            if (!$info) {
                continue;
            }

            /** @var HasOne|HasMany|HasOneThrough|HasManyThrough|BelongsTo|BelongsToMany $attr */
            $attr = $info['attribute'];

            $relation = match ($info['type']) {
                RelationType::HasOne => new HasOneRelation(
                    $models[0], $attr->model, $attr->foreignKey, $attr->localKey
                ),
                RelationType::HasMany => new HasManyRelation(
                    $models[0],
                    $attr->model,
                    $attr->foreignKey,
                    $attr->localKey
                ),
                RelationType::HasOneThrough => new HasOneThroughRelation(
                    $models[0], $attr->model, $attr->through,
                    $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
                ),
                RelationType::HasManyThrough => new HasManyThroughRelation(
                    $models[0], $attr->model, $attr->through,
                    $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
                ),
                RelationType::BelongsTo => new BelongsToRelation(
                    $models[0], $attr->model, $attr->foreignKey, $attr->ownerKey
                ),
                RelationType::BelongsToMany => new BelongsToManyRelation(
                    $models[0], $attr->model, $attr->through,
                    $attr->foreignKey, $attr->relatedKey, $attr->localKey, $attr->relatedPivotKey
                ),
                default => null,
            };

            if ($relation === null) {
                continue;
            }

            $loadedInstances[$name] = $relation->eagerLoad($models, $name);
        }

        // 第三遍：对每层父关系的全部实例递归预加载嵌套关系
        foreach ($nestedMap as $parent => $nested) {
            $instances = $loadedInstances[$parent] ?? [];
            if (!empty($instances)) {
                $this->eagerLoadRelations($instances, array_values(array_unique($nested)));
            }
        }
    }


}
