<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

/**
 * 软删除注解
 *
 * 用法：
 *  #[SoftDelete]                          → 列名 deleted_time
 *  #[SoftDelete('custom_deleted_at')]     → 列名 custom_deleted_at
 *  #[SoftDelete(enabled: false)]          → 禁用软删除
 */
#[Attribute(Attribute::TARGET_CLASS)]
class SoftDelete
{
    public function __construct(
        public readonly string $column = 'deleted_time',
        public readonly bool $enabled = true,
    ) {
    }
}
