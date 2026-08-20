<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Enums\RelationType;

/**
 * Class ModelMeta 模型元数据值对象
 *
 * 由 resolveMeta() 反射解析一次后不可变缓存，取代裸数组传递：
 * - 类型化访问，避免 getMeta()['key'] 的全量数组拷贝与魔法字符串
 * - 只读语义，元数据在进程内恒定
 *
 * @author 原点 <467490186@qq.com>
 */
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
     * @param array<string, string|null> $jsonColumns 属性名→反序列化目标类（null → 原生数组）
     * @param array<string, bool> $nullable 属性名→是否可空
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
        public readonly array $jsonColumns,
        public readonly array $nullable,
    ) {
    }
}