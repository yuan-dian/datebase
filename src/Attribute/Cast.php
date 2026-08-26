<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

/**
 * 类型转换注解
 *
 * 支持两种语法：
 *
 * 字符串语法（简洁）：
 *   #[Cast('int')]
 *   #[Cast('datetime:Y-m-d')]
 *   #[Cast('json:ProductOptions')]
 *   #[Cast('int[]')]
 *
 * 类引用语法（IDE 友好）：
 *   #[Cast(IntegerCaster::class)]
 *   #[Cast(DateTimeCaster::class, format: 'Y-m-d')]
 *   #[Cast(JsonCaster::class, castTo: ProductOptions::class)]
 *   #[Cast(ArrayCaster::class, elements: IntegerCaster::class)]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Cast
{
    public function __construct(
        /**
         * Caster 类名或类型字符串
         * @var class-string<\yuandian\Database\Cast\Caster>|string
         */
        public readonly string $caster,
        
        /**
         * JsonCaster: 目标类名
         */
        public readonly ?string $castTo = null,
        
        /**
         * DateTimeCaster: 日期格式
         */
        public readonly ?string $format = null,
        
        /**
         * ArrayCaster: 元素类型 Caster 类名或类型字符串
         */
        public readonly ?string $elements = null,
    ) {
    }
}
