<?php
/**
 * cursor() 游标迭代测试（SQLite :memory:）
 *
 * 验证点：
 * 1. 正常连接下 cursor() 逐行迭代，SQL 执行与 select() 等价
 * 2. 未连接时 cursor() 不再 false->prepare() fatal（修复前直接崩溃），
 *    而是走惰性连接，抛可捕获的异常（配置缺失/表不存在）
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Db\Connector\Sqlite;
use yuandian\Database\Db\Query;
use yuandian\Database\Facade\DB;

$failures = 0;
function check(bool $cond, string $label): void
{
    echo ($cond ? '  PASS: ' : '  FAIL: ') . $label . "\n";
    if (!$cond) {
        $GLOBALS['failures']++;
    }
}

DB::setConfig([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['type' => 'sqlite', 'database' => ':memory:']],
]);

$conn = DB::connect();
$conn->execute('CREATE TABLE cursor_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(20))');
$conn->execute("INSERT INTO cursor_items (name) VALUES ('a'), ('b'), ('c')");

// ===================== 测试 1: 正常连接 cursor() 逐行迭代 =====================

echo "\n== 测试 1: 正常连接 cursor() 逐行迭代 ==\n";

$rows = [];
foreach (DB::table('cursor_items')->order('id', 'asc')->cursor() as $row) {
    $rows[] = $row;
}
check(count($rows) === 3, '迭代出 3 行');
check($rows[0]['name'] === 'a' && $rows[1]['name'] === 'b' && $rows[2]['name'] === 'c', '顺序与内容正确');
check(isset($rows[0]['id']) && (int) $rows[0]['id'] === 1, '行含 id 字段');

// ===================== 测试 2: 未连接时 cursor() 惰性连接而非 fatal =====================

echo "\n== 测试 2: 未连接时 cursor() 惰性连接而非 fatal ==\n";

// 手动构造未连接的 Sqlite 连接（linkID 为 null），修复前 getPdo() 返回 false → prepare() on bool 直接 fatal
$rawConn = new Sqlite(['type' => 'sqlite', 'database' => ':memory:']);
$query = new Query($rawConn, 'no_table');

try {
    iterator_to_array($query->cursor());
    check(false, '未连接场景应抛出异常（不应静默通过）');
} catch (\Throwable $e) {
    $isBoolPrepare = $e instanceof \Error && str_contains($e->getMessage(), 'prepare()');
    check(!$isBoolPrepare, '未连接时不再 false->prepare() fatal，实际抛：' . get_class($e) . ': ' . $e->getMessage());
}

// ===================== 汇总 =====================

echo "\n" . ($failures === 0 ? 'ALL PASS' : "{$failures} FAILED") . "\n";
exit($failures === 0 ? 0 : 1);
