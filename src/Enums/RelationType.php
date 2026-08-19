<?php

declare(strict_types=1);

namespace yuandian\Database\Enums;

/**
 * 关联类型枚举
 */
enum RelationType: string
{
    /** 一对一 */
    case HasOne = 'HasOne';

    /** 一对多 */
    case HasMany = 'HasMany';

    /** 远程一对一 */
    case HasOneThrough = 'HasOneThrough';

    /** 远程一对多 */
    case HasManyThrough = 'HasManyThrough';
}
