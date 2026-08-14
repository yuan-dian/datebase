<?php

declare(strict_types=1);

namespace yuandian\Database\Enums;

/**
 * 主键策略枚举
 */
enum IdType: string
{
    /** 数据库自增 */
    case AUTO = 'auto';

    /** 雪花算法（数字型） */
    case ASSIGN_ID = 'assign_id';

    /** UUID（字符串型） */
    case ASSIGN_UUID = 'assign_uuid';
}
