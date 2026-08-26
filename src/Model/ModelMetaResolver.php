<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\Cast;
use yuandian\Database\Attribute\Connection;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Attribute\BelongsTo;
use yuandian\Database\Attribute\BelongsToMany;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Enums\RelationType;
use yuandian\Tools\reflection\ClassReflector;
use yuandian\Tools\reflection\PropertyReflection;
use yuandian\Tools\utils\StrUtil;

class ModelMetaResolver
{
    /** @var array<class-string, array{type: RelationType, attribute: object}> */
    private static array $relationMap = [
        HasOne::class         => RelationType::HasOne,
        HasMany::class        => RelationType::HasMany,
        HasOneThrough::class  => RelationType::HasOneThrough,
        HasManyThrough::class => RelationType::HasManyThrough,
        BelongsTo::class      => RelationType::BelongsTo,
        BelongsToMany::class  => RelationType::BelongsToMany,
    ];

    public static function resolve(string $class): ModelMeta
    {
        $reflection = new ClassReflector($class);

        $tableAttr = $reflection->getAttribute(Table::class);
        $tableName = $tableAttr ? $tableAttr->name : StrUtil::snake($reflection->getShortName());

        $connAttr = $reflection->getAttribute(Connection::class);
        $connectionName = $connAttr ? $connAttr->name : null;

        $softDelete = $reflection->getAttributeFromHierarchy(SoftDelete::class);
        $autoWriteTime = $reflection->getAttributeFromHierarchy(AutoWriteTime::class);

        $pkProperty = 'id';
        $pkColumn = 'id';
        $pkType = IdType::AUTO;
        $fields = [];
        $relations = [];
        $propertyTypes = [];
        $nullable = [];

        foreach ($reflection->getPublicProperties() as $prop) {
            $propName = $prop->getName();

            if (str_starts_with($propName, '_')) {
                continue;
            }

            $type = $prop->getType();
            if ($type === null || $type->allowsNull()) {
                $nullable[$propName] = true;
            }

            $relation = self::parseRelations($prop);
            if ($relation) {
                $relations[$propName] = $relation;
            } else {
                $fields[$propName] = StrUtil::snake($propName);
            }

            $tableIdAttr = $prop->getAttribute(TableId::class);
            if ($tableIdAttr) {
                $pkProperty = $propName;
                $pkColumn = StrUtil::snake($propName);
                $pkType = $tableIdAttr->type;
            }

            $castAttr = $prop->getAttribute(Cast::class);
            if ($castAttr) {
                $propertyTypes[$propName] = $castAttr;
            } elseif ($type !== null && $type->isBuiltin()) {
                $propertyTypes[$propName] = $type->getName();
            } elseif ($type !== null && !$type->isBuiltin()) {
                $propertyTypes[$propName] = $type->getName();
            }
        }

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
            $propertyTypes,
            $nullable,
            $eventMethods,
        );
    }

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
