<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\Connection;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Attribute\BelongsTo;
use yuandian\Database\Attribute\BelongsToMany;
use yuandian\Database\Attribute\JsonColumn;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Enums\RelationType;
use yuandian\Tools\reflection\ClassReflector;
use yuandian\Tools\reflection\PropertyReflection;
use yuandian\Tools\utils\StrUtil;

/**
 * 模型元数据解析器：通过反射读取注解，构建 ModelMeta 值对象。
 *
 * 从 Model::resolveMeta() 提取，职责单一——仅做反射解析，不做缓存。
 * 缓存由 Model::getMeta() 管理（按类名静态缓存）。
 *
 * @date 2026/8/21
 * @author 原点 467490186@qq.com
 */
class ModelMetaResolver
{
    /** @var array<class-string, array{type: RelationType, attribute: object}> 关联注解类→结果 */
    private static array $relationMap = [
        HasOne::class         => RelationType::HasOne,
        HasMany::class        => RelationType::HasMany,
        HasOneThrough::class  => RelationType::HasOneThrough,
        HasManyThrough::class => RelationType::HasManyThrough,
        BelongsTo::class      => RelationType::BelongsTo,
        BelongsToMany::class  => RelationType::BelongsToMany,
    ];

    /**
     * 解析模型类的元数据，返回不可变值对象。
     *
     * @param class-string $class 模型类名
     */
    public static function resolve(string $class): ModelMeta
    {
        $reflection = new ClassReflector($class);

        // 类级注解
        $tableAttr = $reflection->getAttribute(Table::class);
        $tableName = $tableAttr ? $tableAttr->name : StrUtil::snake($reflection->getShortName());

        $connAttr = $reflection->getAttribute(Connection::class);
        $connectionName = $connAttr ? $connAttr->name : null;

        $softDelete = $reflection->getAttributeFromHierarchy(SoftDelete::class);
        $autoWriteTime = $reflection->getAttributeFromHierarchy(AutoWriteTime::class);

        // 属性级解析
        $pkProperty = 'id';
        $pkColumn = 'id';
        $pkType = IdType::AUTO;
        $fields = [];
        $relations = [];
        $jsonColumns = [];
        $nullable = [];

        foreach ($reflection->getPublicProperties() as $prop) {
            $propName = $prop->getName();

            if (str_starts_with($propName, '_')) {
                continue;
            }

            // nullable 判定
            $type = $prop->getType();
            if ($type === null || $type->allowsNull()) {
                $nullable[$propName] = true;
            }

            // 关联 vs 普通字段
            $relation = self::parseRelations($prop);
            if ($relation) {
                $relations[$propName] = $relation;
            } else {
                $fields[$propName] = StrUtil::snake($propName);
            }

            // 主键
            $tableIdAttr = $prop->getAttribute(TableId::class);
            if ($tableIdAttr) {
                $pkProperty = $propName;
                $pkColumn = StrUtil::snake($propName);
                $pkType = $tableIdAttr->type;
            }

            // JSON 列
            $jsonColumnAttr = $prop->getAttribute(JsonColumn::class);
            if ($jsonColumnAttr) {
                $jsonColumns[$propName] = $jsonColumnAttr->castTo;
            }
        }

        // 事件方法扫描：检查模型是否声明了 on{Event} 方法
        $eventNames = [
            'beforeInsert', 'afterInsert', 'beforeUpdate', 'afterUpdate',
            'beforeDelete', 'afterDelete', 'beforeForceDelete', 'afterForceDelete',
            'beforeRestore', 'afterRestore', 'afterRead',
        ];
        $eventMethods = [];
        foreach ($eventNames as $event) {
            $method = 'on' . ucfirst($event);
            if (method_exists($class, $method)) {
                $eventMethods[] = $method;
            }
        }

        return new ModelMeta(
            $tableName,
            $connectionName,
            $softDelete,
            $autoWriteTime,
            $fields,
            $pkProperty,
            $pkColumn,
            $pkType,
            $relations,
            $jsonColumns,
            $nullable,
            $eventMethods,
        );
    }

    /**
     * 解析属性上的关联注解（HasOne/HasMany/HasOneThrough/HasManyThrough）。
     *
     * @return array{type: RelationType, attribute: object}|[]  关联定义或空数组（非关联属性）
     */
    private static function parseRelations(PropertyReflection $prop): array
    {
        foreach ($prop->getReflection()->getAttributes() as $attribute) {
            $attrClass = $attribute->getName();
            foreach (self::$relationMap as $targetClass => $type) {
                if (is_a($attrClass, $targetClass, true)) {
                    return [
                        'type'      => $type,
                        'attribute' => $attribute->newInstance(),
                    ];
                }
            }
        }

        return [];
    }
}
