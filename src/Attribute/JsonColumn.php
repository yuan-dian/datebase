<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

/**
 * JSON 列注解
 *
 * 标记属性在数据库中以 JSON 字符串存储，
 * 读取时自动反序列化，写入时自动序列化。
 *
 * 用法：
 *   #[JsonColumn]                          → array
 *   #[JsonColumn(ProductOptions::class)]   → 单个对象
 *   #[JsonColumn(Item::class)]             → array 属性 + 对象 → 对象数组
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class JsonColumn
{
    /**
     * @param string|null $castTo 目标类名，null 表示解码为原生数组
     */
    public function __construct(public readonly ?string $castTo = null)
    {
    }
}
