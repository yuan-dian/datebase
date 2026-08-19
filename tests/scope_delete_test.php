<?php
// +----------------------------------------------------------------------
// | 全局作用域/软删除验证（SQLite :memory: 无外部依赖）
// | 验证点：
// |   1. force()->delete() 物理删除已软删行（作用域判断先于软删过滤）
// |   2. 无 force 时 delete 已软删行命中 0 行（软删过滤生效）
// +----------------------------------------------------------------------

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Model;

// ===================== 测试模型 =====================

#[Table('scope_post')]
#[SoftDelete]
class ScopePost extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $title = '';
}

// ===================== 断言工具 =====================

$GLOBALS['failures'] = 0;

function check(bool $cond, string $label): void
{
    if ($cond) {
        echo "  PASS: {$label}\n";
    } else {
        $GLOBALS['failures']++;
        echo "  FAIL: {$label}\n";
    }
}

// ===================== 环境 =====================

DB::setConfig([
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'type'     => 'sqlite',
            'database' => ':memory:',
        ],
    ],
]);

$conn = DB::connect();
$conn->execute('CREATE TABLE scope_post (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(50), deleted_time DATETIME NULL)');
$conn->execute("INSERT INTO scope_post (title) VALUES ('a'), ('b'), ('c')");

// ===================== 测试 1: force()->delete() 物理删除已软删行 =====================

echo "\n== 测试 1: force()->delete() 物理删除已软删行 ==\n";

// 软删 id=2（deleted_time 写入）
$softCount = ScopePost::where('id', '=', 2)->delete();
check($softCount === 1, '软删 id=2 影响 1 行');

// 软删后正常查询看不到 id=2
$visible = ScopePost::select();
check(count($visible) === 2, '软删后可见 2 行');
check(!in_array(2, array_column(array_map(fn($m) => ['id' => $m->id], $visible), 'id'), true), 'id=2 不可见');

// force()->delete() 应物理删除已软删的 id=2（修复前此处命中 0 行）
$forceCount = ScopePost::force()->where('id', '=', 2)->delete();
check($forceCount === 1, 'force()->delete() 物理删除已软删行影响 1 行（修复前为 0）');

// 物理删除后连软删行也查不到（无 deleted_time 的行）
$all = ScopePost::withoutGlobalScopes()->select();
check(count($all) === 2, '物理删除后全部行仅剩 2 行');

// ===================== 测试 2: 无 force 时 delete 已软删行命中 0 行 =====================

echo "\n== 测试 2: 无 force 时 delete 已软删行命中 0 行 ==\n";

// 再软删 id=1
$softCount2 = ScopePost::where('id', '=', 1)->delete();
check($softCount2 === 1, '软删 id=1 影响 1 行');

// 无 force delete 已软删行：软删过滤使 WHERE 不命中 → 0 行
$again = ScopePost::where('id', '=', 1)->delete();
check($again === 0, '无 force delete 已软删行影响 0 行');

// 该行仍在（deleted_time 非空）
$all2 = ScopePost::withoutGlobalScopes()->select();
check(count($all2) === 2, 'id=1 仍在（软删未物理删除）');
check(!in_array(2, array_map(fn($m) => $m->id, $all2), true), 'id=2 已物理删除');

// ===================== 测试 3: force()->delete() 正常行物理删除 =====================

echo "\n== 测试 3: force()->delete() 正常行物理删除 ==\n";

$forceNormal = ScopePost::force()->where('id', '=', 3)->delete();
check($forceNormal === 1, 'force 删除正常行影响 1 行');

$all3 = ScopePost::withoutGlobalScopes()->select();
check(count($all3) === 1, '剩余 1 行');
check($all3[0]->id === 1, '仅剩 id=1（软删状态）');

// ===================== 汇总 =====================

echo "\n" . ($GLOBALS['failures'] === 0 ? 'ALL PASS' : $GLOBALS['failures'] . ' FAILED') . "\n";
exit($GLOBALS['failures'] === 0 ? 0 : 1);