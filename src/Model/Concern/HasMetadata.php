<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Enums\RelationType;
use yuandian\Database\Model\ModelMeta;
use yuandian\Database\Model\ModelMetaResolver;

/**
 * 模型元数据代理：将 ModelMeta 的字段委托为静态便捷方法。
 *
 * @date 2026/8/24
 * @author 原点 <467490186@qq.com>
 */
trait HasMetadata
{
    /** @var array<class-string, ModelMeta> 类名→元数据值对象 */
    protected static array $metaCache = [];

    public static function getMeta(): ModelMeta
    {
        $class = static::class;
        if (!isset(self::$metaCache[$class])) {
            self::$metaCache[$class] = ModelMetaResolver::resolve($class);
        }
        return self::$metaCache[$class];
    }

    public static function getTableName(): string
    {
        return self::getMeta()->table;
    }

    public static function getConnectionName(): ?string
    {
        return self::getMeta()->connection;
    }

    public static function getSoftDelete(): ?SoftDelete
    {
        return self::getMeta()->softDelete;
    }

    public static function getAutoWriteTime(): ?AutoWriteTime
    {
        return self::getMeta()->autoWriteTime;
    }

    public static function getPkProperty(): string
    {
        return self::getMeta()->pkProperty;
    }

    public static function getPkColumn(): string
    {
        return self::getMeta()->pkColumn;
    }

    public static function getPkType(): IdType
    {
        return self::getMeta()->pkType;
    }

    /**
     * @return array<string, string> [propertyName => columnName]
     */
    public static function getColumnMap(): array
    {
        return self::getMeta()->fields;
    }

    /**
     * 获取关联元数据
     * @return array{type: RelationType, attribute: object}|null
     */
    public static function getRelationInfo(string $name): ?array
    {
        return self::getMeta()->relations[$name] ?? null;
    }

    /**
     * @return array<string, \yuandian\Database\Attribute\Cast>
     */
    public static function getPropertyTypes(): array
    {
        return self::getMeta()->propertyTypes;
    }
}
