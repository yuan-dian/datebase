<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

/**
 * 软删除注解
 *
 * 用法：
 *  #[SoftDelete]                              → 列名 deleted_time，默认值 null
 *  #[SoftDelete('custom_deleted_at')]         → 列名 custom_deleted_at
 *  #[SoftDelete(column: 'deleted', default: '0000-00-00 00:00:00')] → 自定义列与默认值
 *  #[SoftDelete(enabled: false)]              → 禁用软删除
 */
#[Attribute(Attribute::TARGET_CLASS)]
class SoftDelete
{
    public function __construct(
        public readonly string $column = 'deleted_time',
        public readonly bool $enabled = true,
        public readonly ?string $default = null,
    ) {
    }

    /**
     * 软删除是否启用
     */
    public function active(): bool
    {
        return $this->enabled;
    }
}
