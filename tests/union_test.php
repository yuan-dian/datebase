<?php

declare(strict_types=1);

/**
 * 验证点：union + ORDER/LIMIT 模板顺序
 * 修复前：%ORDER%%LIMIT%%UNION% —— 主链 ORDER/LIMIT 拼进首分支，语义错误
 * 修复后：%UNION%%ORDER%%LIMIT% —— 主链 ORDER/LIMIT 作用于 UNION 整体结果
 */

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Facade\DB;

$failures = 0;
$GLOBALS['sqls'] = [];

function check(bool $cond, string $label): void
{
    global $failures;
    echo ($cond ? '  PASS: ' : '  FAIL: ') . $label . "\n";
    if (!$cond) {
        $failures++;
    }
}

DB::setConfig([
    'default' => 'sqlite',
    'connections' => ['sqlite' => ['type' => 'sqlite', 'database' => ':memory:']],
]);
DB::listen('sql', function (string $sql) {
    $GLOBALS['sqls'][] = $sql;
});

$conn = DB::connect();
$conn->execute('CREATE TABLE union_a (id INTEGER PRIMARY KEY AUTOINCREMENT, val INTEGER)');
$conn->execute('CREATE TABLE union_b (id INTEGER PRIMARY KEY AUTOINCREMENT, val INTEGER)');
$conn->execute("INSERT INTO union_a (val) VALUES (3), (1), (5)");
$conn->execute("INSERT INTO union_b (val) VALUES (2), (4), (6)");

// ===================== 测试 1: union + 整体 ORDER/LIMIT =====================

echo "\n== 测试 1: union + 整体 ORDER/LIMIT（UNION 在 ORDER 前） ==\n";

$GLOBALS['sqls'] = [];
$rows1 = DB::table('union_a')->union(DB::table('union_b'))->order('val', 'asc')->limit(3)->select();

$sql1 = $GLOBALS['sqls'][0] ?? '';
$posUnion = strpos($sql1, 'UNION');
$posOrder = strpos($sql1, 'ORDER BY');
check($posUnion !== false && $posOrder !== false && $posUnion < $posOrder, 'SQL 中 UNION 位于 ORDER BY 之前（修复前相反）');
check(strpos($sql1, 'LIMIT 3') !== false, '整体 LIMIT 3 在 UNION 之后');

$vals1 = array_column($rows1, 'val');
check(count($vals1) === 3 && $vals1 === [1, 2, 3], '整体排序分页结果 val=1,2,3（修复前为 6 行或首分支排序）');

// ===================== 测试 2: 子查询自带 ORDER/LIMIT 保留 =====================

echo "\n== 测试 2: 子查询自带 ORDER/LIMIT 保留 ==\n";

$GLOBALS['sqls'] = [];
$rows2 = DB::table('union_a')->union(DB::table('union_b')->where('id', '>', 1)->order('val', 'desc')->limit(1))->select();

$sql2 = $GLOBALS['sqls'][0] ?? '';
check(strpos($sql2, 'ORDER BY') !== false, '子查询内部 ORDER BY 保留');
check(strpos($sql2, '( SELECT') !== false, '子查询带 ORDER/LIMIT 用派生表包裹（否则解释为整体排序分页）');
check(substr_count($sql2, 'SELECT') === 3, '三个 SELECT（主查询 + 派生表外层 + 子查询内层）');
check(count($rows2) === 4, '结果 4 行（a 3 行 + b 限 1 行，修复前仅 1 行）');

// ===================== 测试 3: 无 union 时模板不变（回归） =====================

echo "\n== 测试 3: 无 union 时 ORDER/LIMIT 正常 ==\n";

$rows3 = DB::table('union_a')->order('id', 'asc')->limit(2)->select();
check(count($rows3) === 2 && $rows3[0]['id'] === 1 && $rows3[1]['id'] === 2, '无 union 普通排序分页不变');

// ===================== 测试 4: union() type 白名单（防 SQL 注入） =====================

echo "\n== 测试 4: union() type 白名单（防 SQL 注入） ==\n";

// 恶意 type：白名单外一律回退 UNION，注入串不得进入 SQL
$GLOBALS['sqls'] = [];
$rows4 = DB::table('union_a')->union(DB::table('union_b'), 'UNION; DROP TABLE union_a; --')->select();
$sql4 = $GLOBALS['sqls'][0] ?? '';
check(strpos($sql4, 'DROP TABLE') === false, '恶意 type 不进入 SQL（注入串被白名单拦截）');
check(substr_count($sql4, 'UNION') === 1, '恶意 type 回退为单一 UNION');
check(count($rows4) === 6, '注入 type 下 UNION 结果正常 6 行');

// 合法小写 'union all'：白名单内规范化大写
$GLOBALS['sqls'] = [];
DB::table('union_a')->union(DB::table('union_b'), 'union all')->select();
$sql4b = $GLOBALS['sqls'][0] ?? '';
check(strpos($sql4b, 'UNION ALL') !== false, "小写 'union all' 规范化为 UNION ALL");

// ===================== 测试 5: string 分支 union（含 ORDER/LIMIT 走派生表） =====================

echo "\n== 测试 5: string 分支 union ==\n";

// 无 ORDER/LIMIT 的 string：直接拼接（SQLite 不支持 UNION 分支括号）
$GLOBALS['sqls'] = [];
$rows5 = DB::table('union_a')->union('SELECT * FROM union_b')->select();
$sql5 = $GLOBALS['sqls'][0] ?? '';
check(strpos($sql5, 'UNION SELECT * FROM union_b') !== false, '无 ORDER/LIMIT 的 string 直接拼接');
check(count($rows5) === 6, 'string union 结果 6 行');

// 带 ORDER/LIMIT 的 string：派生表包裹限定作用域（与子查询分支一致）
$GLOBALS['sqls'] = [];
$rows5b = DB::table('union_a')->union('SELECT * FROM union_b ORDER BY val DESC LIMIT 1')->select();
$sql5b = $GLOBALS['sqls'][0] ?? '';
check(strpos($sql5b, 'SELECT * FROM ( SELECT * FROM union_b ORDER BY val DESC LIMIT 1 ) AS t') !== false, '带 ORDER/LIMIT 的 string 用派生表 AS t 包裹');
check(count($rows5b) === 4, 'string 派生表结果 4 行（a 3 + b 限 1）');

// ===================== 测试 6: 派生表 AS t 别名（子查询分支，MySQL/Oracle 必需） =====================

echo "\n== 测试 6: 派生表 AS t 别名 ==\n";

$GLOBALS['sqls'] = [];
DB::table('union_a')->union(DB::table('union_b')->order('val', 'desc')->limit(2))->select();
$sql6 = $GLOBALS['sqls'][0] ?? '';
check(strpos($sql6, ') AS t') !== false, '子查询分支派生表带 AS t 别名');

// ===================== 汇总 =====================

echo "\n" . ($failures === 0 ? 'ALL PASS' : "{$failures} FAILED") . "\n";
exit($failures === 0 ? 0 : 1);
