<?php

declare(strict_types=1);

namespace yuandian\Database\Attribute;

use Attribute;

/**
 * 自动时间写入
 *
 * 用法：
 *  #[AutoWriteTime]                          → 列名 create_time，update_time
 *  #[AutoWriteTime(enabled: false)]          → 禁用
 *  #[AutoWriteTime(createTime: 'create_at')] → 列名 create_at
 *  #[AutoWriteTime(createTime: false)]       → 禁用 createTime
 *  #[AutoWriteTime(updateTime: 'update_at')] → 列名 update_at
 *  #[AutoWriteTime(updateTime: false)]       → 禁用 updateTime
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AutoWriteTime
{
    public function __construct(
        public readonly bool $enabled = true,
        public readonly string|bool $createTime = 'create_time',
        public readonly string|bool $updateTime = 'update_time',
    ) {
    }
}
