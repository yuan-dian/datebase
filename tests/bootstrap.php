<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2026/8/17
// +----------------------------------------------------------------------
// 测试公共引导：数据库配置 + 断言辅助函数
// 依赖：MySQL 127.0.0.1 database（见 src/Config/database.php）

declare(strict_types=1);

use yuandian\Database\Facade\DB;

$GLOBALS['__check_pass'] = 0;
$GLOBALS['__check_fail'] = 0;

/**
 * 数据库连接配置（覆盖库默认，避免读取含生产凭据的 Config/database.php）
 */
function test_db_config(): array
{
    return [
        'default'     => 'mysql',
        'connections' => [
            'mysql' => [
                'type'            => 'mysql',
                'hostname'        => '127.0.0.1',
                'database'        => 'database',
                'username'        => 'root',
                'password'        => 'REMOVED',
                'hostport'        => 3306,
                'params'          => [
                    \PDO::ATTR_TIMEOUT => 3,
                ],
                'charset'         => 'utf8',
                'prefix'          => '',
                'break_reconnect' => true,
            ],
        ],
    ];
}

/**
 * 断言：记录结果并输出
 */
function check(bool $condition, string $message): void
{
    if ($condition) {
        $GLOBALS['__check_pass']++;
        echo "  [PASS] {$message}\n";
    } else {
        $GLOBALS['__check_fail']++;
        echo "  [FAIL] {$message}\n";
    }
}

/**
 * 输出测试统计
 */
function check_summary(string $title = '测试结果'): void
{
    $pass = $GLOBALS['__check_pass'];
    $fail = $GLOBALS['__check_fail'];
    echo "\n==========================================\n";
    echo "{$title}: {$pass} 通过, {$fail} 失败\n";
    exit($fail > 0 ? 1 : 0);
}

DB::setConfig(test_db_config());