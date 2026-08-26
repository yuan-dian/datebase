<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\Cast;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Cast\Caster;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Enums\RelationType;

final class ModelMeta
{
    /**
     * @param string $table 表名
     * @param string|null $connection 连接名（null → 默认连接）
     * @param SoftDelete|null $softDelete 软删除注解实例
     * @param AutoWriteTime|null $autoWriteTime 自动时间戳注解实例
     * @param array<string, string> $fields 属性名→列名 映射
     * @param string $pkProperty 主键属性名
     * @param string $pkColumn 主键列名
     * @param IdType $pkType 主键生成策略
     * @param array<string, array{type: RelationType, attribute: object}> $relations 属性名→关联定义
     * @param array<string, Cast|string> $propertyTypes 属性名→类型信息（#[Cast] 实例或 PHP 类型名）
     * @param array<string, bool> $nullable 属性名→是否可空
     * @param list<string> $eventMethods 模型上已声明的事件方法名
     * @param array<string, Caster> $casters 属性名→Caster 实例（解析阶段预构建，避免热路径重复调用）
     */
    public function __construct(
        public readonly string $table,
        public readonly ?string $connection,
        public readonly ?SoftDelete $softDelete,
        public readonly ?AutoWriteTime $autoWriteTime,
        public readonly array $fields,
        public readonly string $pkProperty,
        public readonly string $pkColumn,
        public readonly IdType $pkType,
        public readonly array $relations,
        public readonly array $propertyTypes = [],
        public readonly array $nullable = [],
        public readonly array $eventMethods = [],
        public readonly array $casters = [],
    ) {
    }
}
