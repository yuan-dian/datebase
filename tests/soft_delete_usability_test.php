<?php
// +----------------------------------------------------------------------
// | 软删除易用性 API 验证（SQLite :memory: 无外部依赖）
// | 验证点：
// |   1. withTrashed() / onlyTrashed() 查询
// |   2. isTrashed() 实例状态（水合时软删列非空即标记）
// |   3. restore() 实例 + 批量恢复
// |   4. delete() 幂等 + 实例状态同步
// |   5. forceDelete() 物理删除
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

#[Table('usability_post')]
#[SoftDelete]
class UsabilityPost extends Model
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
$conn->execute('CREATE TABLE usability_post (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(50), deleted_time DATETIME NULL)');
$conn->execute("INSERT INTO usability_post (title) VALUES ('a'), ('b'), ('c')");

// ===================== 测试 1: 默认过滤 + withTrashed/onlyTrashed =====================

echo "\n== 测试 1: 默认过滤 + withTrashed/onlyTrashed ==\n";

// 软删 id=2
UsabilityPost::where('id', '=', 2)->delete();

// 默认过滤：仅见 2 行
$visible = UsabilityPost::select();
check(count($visible) === 2, '默认查询排除已删行（2 行）');

// withTrashed：含已删行共 3 行
$all = UsabilityPost::withTrashed()->select();
check(count($all) === 3, 'withTrashed() 可见全部 3 行');

// onlyTrashed：仅已删行 1 行
$trashed = UsabilityPost::onlyTrashed()->select();
check(count($trashed) === 1 && $trashed[0]->id === 2, 'onlyTrashed() 仅见已删行');

// 链式 withTrashed（实例查询对象）
$chain = UsabilityPost::query()->withTrashed()->select();
check(count($chain) === 3, '链式 withTrashed() 可见全部 3 行');

// ===================== 测试 2: isTrashed 实例状态（水合标记） =====================

echo "\n== 测试 2: isTrashed 实例状态 ==\n";

// 水合已删行 → isTrashed() === true
$trashedModel = UsabilityPost::withTrashed()->where('id', '=', 2)->find();
check($trashedModel !== null && $trashedModel->isTrashed() === true, '水合已删行 isTrashed() === true');

// 水合未删行 → isTrashed() === false
$liveModel = UsabilityPost::where('id', '=', 1)->find();
check($liveModel !== null && $liveModel->isTrashed() === false, '水合未删行 isTrashed() === false');

// 新实例（未持久化）→ isTrashed() === false
$fresh = new UsabilityPost();
check($fresh->isTrashed() === false, '新实例 isTrashed() === false');

// ===================== 测试 3: restore 实例 + 批量 =====================

echo "\n== 测试 3: restore 实例 + 批量 ==\n";

// 批量恢复已删 id=2
$restoredCount = UsabilityPost::where('id', '=', 2)->restore();
check($restoredCount === 1, '批量 restore() 恢复 id=2 影响 1 行');

// 恢复后正常查询可见
$afterRestore = UsabilityPost::select();
check(count($afterRestore) === 3, '恢复后可见 3 行');

// 实例 restore：软删 id=3 → isTrashed true → restore → isTrashed false
$m3 = UsabilityPost::where('id', '=', 3)->find();
$m3->delete();
check($m3->isTrashed() === true, '实例 delete() 后 isTrashed() === true');
check($m3->restore() === true, '实例 restore() 返回 true');
check($m3->isTrashed() === false, '实例 restore() 后 isTrashed() === false');
check(UsabilityPost::where('id', '=', 3)->find() !== null, '实例 restore() 后查询可见');

// 未启用软删模型 restore 抛异常
try {
    UsabilityPost::query()->withoutGlobalScope('softDelete');
    DB::table('usability_post')->restore();
    check(false, 'Db 层 Query 无 restore()（应抛错误）');
} catch (\Throwable $e) {
    check(true, 'Db 层 Query 无 restore() 方法');
}

// ===================== 测试 4: delete 幂等 + 实例状态同步 =====================

echo "\n== 测试 4: delete 幂等 + 实例状态同步 ==\n";

// 软删 id=1 → isTrashed true + exists false
$m1 = UsabilityPost::where('id', '=', 1)->find();
$m1->delete();
check($m1->isTrashed() === true, '实例 delete() 后 isTrashed() === true');
check($m1->exists() === false, '实例 delete() 后 exists() === false');

// 重复软删（批量）幂等返回 1
$again = UsabilityPost::where('id', '=', 1)->delete();
check($again === 1, '重复软删已删行影响 1 行（幂等）');

// ===================== 测试 5: forceDelete 物理删除 =====================

echo "\n== 测试 5: forceDelete 物理删除 ==\n";

// 实例 forceDelete：物理删除 id=1（已软删）
$m1b = UsabilityPost::withTrashed()->where('id', '=', 1)->find();
check($m1b !== null && $m1b->forceDelete() === true, '实例 forceDelete() 物理删除返回 true');

// 物理删除后连 withTrashed 也查不到
$gone = UsabilityPost::withTrashed()->where('id', '=', 1)->find();
check($gone === null, 'forceDelete 后 withTrashed 也查不到');

// 批量 force：物理删除 id=2
$forceCount = UsabilityPost::force()->where('id', '=', 2)->delete();
check($forceCount === 1, '批量 force()->delete() 物理删除影响 1 行');

// 剩余仅 id=3
$left = UsabilityPost::withTrashed()->select();
check(count($left) === 1 && $left[0]->id === 3, '仅剩 id=3');

// ===================== 测试 6: withGlobalScope 反向恢复 =====================

echo "\n== 测试 6: withGlobalScope 反向恢复 ==\n";

// 软删 id=3
UsabilityPost::where('id', '=', 3)->delete();

// withoutGlobalScope 后 withGlobalScope 恢复过滤
$withTrashed = UsabilityPost::withTrashed()->select();
check(count($withTrashed) === 1, 'withTrashed 见 1 行');
$restored = UsabilityPost::withTrashed()->withGlobalScope('softDelete')->select();
check(count($restored) === 0, 'withGlobalScope(\'softDelete\') 恢复默认过滤');

// ===================== 汇总 =====================

echo "\n" . ($GLOBALS['failures'] === 0 ? 'ALL PASS' : $GLOBALS['failures'] . ' FAILED') . "\n";
exit($GLOBALS['failures'] === 0 ? 0 : 1);