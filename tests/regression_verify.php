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
// 核心回归验证：dirty 检测、空数组保留、insertAll 短路、聚合、分页、fetchSql、
// 软删除、AutoWriteTime、JSON 列、IdType 变体、Db 层独立使用
// 使用 tmp_reg_* 测试表（幂等重建），不影响业务表

declare(strict_types=1);

use yuandian\Database\Db\Raw;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\model\RegAutoModel;
use yuandian\Database\Tests\model\RegSnowModel;
use yuandian\Database\Tests\model\RegUuidModel;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

// ---------- 建表（幂等） ----------
$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=database;charset=utf8mb4',
    'root',
    'REMOVED'
);
$pdo->exec('DROP TABLE IF EXISTS tmp_reg_auto');
$pdo->exec('DROP TABLE IF EXISTS tmp_reg_snow');
$pdo->exec('DROP TABLE IF EXISTS tmp_reg_uuid');
$pdo->exec('CREATE TABLE tmp_reg_auto (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL DEFAULT \'\',
    status TINYINT NOT NULL DEFAULT 0,
    tags TEXT NULL,
    deleted_time DATETIME NULL,
    create_time DATETIME NULL,
    update_time DATETIME NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE tmp_reg_snow (
    id BIGINT PRIMARY KEY,
    name VARCHAR(50) NOT NULL DEFAULT \'\'
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE tmp_reg_uuid (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(50) NOT NULL DEFAULT \'\'
) ENGINE=InnoDB');

echo "开始核心回归验证...\n\n";

// ---------- insert：AUTO 回填 + AutoWriteTime ----------
echo "== insert + AutoWriteTime ==\n";

$m = new RegAutoModel();
$m->name = '回归-插入';
$m->status = 1;
$m->tags = ['a', 'b', 'c'];
$ok = $m->save();
check($ok === true, 'save() 返回 true');
check($m->id > 0, 'AUTO 主键回填 (id=' . $m->id . ')');
check($m->createTime !== null && $m->createTime !== '', 'create_time 自动写入');
check($m->updateTime !== null && $m->updateTime !== '', 'update_time 自动写入');

$dbRow = DB::table('tmp_reg_auto')->where('id', '=', $m->id)->find();
check(is_array($dbRow), 'Db 层查到刚插入的数据');
check($dbRow['tags'] === '["a","b","c"]', 'JSON 列以字符串落库');

// ---------- dirty 检测：fetch 后 save() 不应产生 UPDATE ----------
echo "\n== dirty 检测 (A1) ==\n";

$fetched = RegAutoModel::where('id', '=', $m->id)->find();
check($fetched instanceof RegAutoModel, 'find() 返回模型');
check(is_array($fetched->tags) && $fetched->tags === ['a', 'b', 'c'], 'JSON 列反序列化正确');
check(gettype($fetched->id) === 'integer', 'int 列保持 int 类型');

sleep(1);
$before = $fetched->updateTime;
$fetched->save();
$reFetched = RegAutoModel::where('id', '=', $m->id)->find();
check($reFetched->updateTime === $before, '未改动 save() 不触发 UPDATE（update_time 不变）');

$fetched->name = '回归-改名';
$fetched->save();
$reFetched2 = RegAutoModel::where('id', '=', $m->id)->find();
check($reFetched2->name === '回归-改名', '改动后 save() 生效');
check($reFetched2->status === 1, '未改字段保持原值');
check($reFetched2->updateTime !== $before, '改动后 update_time 被刷新');

// ---------- A2：空数组保留 ----------
echo "\n== toArray 空数组保留 (A2) ==\n";

$m2 = new RegAutoModel();
$m2->name = '空数组';
$m2->tags = [];
$m2->save();
$fetched2 = RegAutoModel::where('id', '=', $m2->id)->find();
$arr = $fetched2->toArray();
check(array_key_exists('tags', $arr) && is_array($arr['tags']) && $arr['tags'] === [], 'toArray 保留空数组 tags');

// ---------- A4：insertAll 空数组短路 ----------
echo "\n== insertAll (A4) ==\n";

$n = RegAutoModel::insertAll([]);
check($n === 0, 'insertAll(空数组) 返回 0');

$rows = [
    ['name' => '批量1', 'status' => 2],
    ['name' => '批量2', 'status' => 2],
    ['name' => '批量3', 'status' => 3],
];
$n = RegAutoModel::insertAll($rows);
check($n === 3, 'insertAll 插入 3 行');
check(DB::table('tmp_reg_auto')->where('name', 'in', ['批量1', '批量2', '批量3'])->count() === 3, '3 行实际落库');

// ---------- 聚合：count/sum + group 子查询 (A8) ----------
echo "\n== 聚合 (A8) ==\n";

$total = RegAutoModel::count();
check($total > 0 && gettype($total) === 'integer', 'count() 返回 int');

$grouped = RegAutoModel::groupBy('status')->count();
$distinctStatus = count(DB::table('tmp_reg_auto')->groupBy('status')->select());
check($grouped === $distinctStatus, 'group+count 返回组数 (' . $grouped . '=' . $distinctStatus . ')');

try {
    RegAutoModel::count("id); DROP TABLE tmp_reg_auto; --");
    check(false, '恶意 field 被拒绝');
} catch (\InvalidArgumentException) {
    check(true, '恶意 field 抛 InvalidArgumentException');
}

// ---------- 分页：options 恢复 (A10) ----------
echo "\n== 分页 (A10) ==\n";

$page = RegAutoModel::paginate(listRows: 2);
check($page->items() !== null && count($page->items()) === 2, 'paginate 每页 2 条');
check($page->total() === $total, 'paginate total 正确');
$after = RegAutoModel::select();
check(count($after) >= $total, 'paginate 后 select 不受 limit 污染');

// ---------- fetchSql ----------
echo "\n== fetchSql ==\n";

$sql = RegAutoModel::where('id', '>', 0)->fetchSql()->select();
check(is_string($sql), 'fetchSql()->select() 返回 string');
check(str_contains($sql, 'SELECT'), 'SQL 含 SELECT');

try {
    RegAutoModel::fetchSql()->paginate(2);
    check(false, 'fetchSql+paginate 应抛异常');
} catch (\yuandian\Database\Exceptions\DbException) {
    check(true, 'fetchSql+paginate 抛 DbException');
}

$valueSql = DB::table('tmp_reg_auto')->where('id', '>', 0)->fetchSql()->value('name');
check(is_string($valueSql) && str_contains($valueSql, 'SELECT'), 'fetchSql()->value() 返回 SQL');

$columnSql = DB::table('tmp_reg_auto')->where('id', '>', 0)->fetchSql()->column('name');
check(is_string($columnSql) && str_contains($columnSql, 'SELECT'), 'fetchSql()->column() 返回 SQL');

try {
    $gen = DB::table('tmp_reg_auto')->where('id', '>', 0)->fetchSql()->cursor();
    $gen->current();
    check(false, 'fetchSql+cursor 应抛异常');
} catch (\yuandian\Database\Exceptions\DbException) {
    check(true, 'fetchSql+cursor 抛 DbException');
}

// ---------- 软删除 ----------
echo "\n== 软删除 ==\n";

$del = RegAutoModel::where('id', '=', $m->id)->find();
$del->delete();
check(RegAutoModel::where('id', '=', $m->id)->find() === null, '软删后默认查询不可见');
$softRow = DB::table('tmp_reg_auto')->where('id', '=', $m->id)->find();
check($softRow['deleted_time'] !== null, 'deleted_time 已写入（物理行仍在）');
$withTrashed = RegAutoModel::withoutGlobalScopes()->where('id', '=', $m->id)->find();
check($withTrashed !== null, 'withoutGlobalScopes 可见软删行');

// ---------- P0-1：order 方向白名单 ----------
echo "\n== P0-1 order 方向白名单 ==\n";

$safeSql = DB::table('tmp_reg_auto')->order('id', 'ASC; DROP TABLE tmp_reg_auto; --')->fetchSql()->select();
check(!str_contains($safeSql, 'DROP'), 'order 方向注入被白名单拦截 (ASC; DROP...)');
check(str_contains($safeSql, 'ORDER BY'), 'order 仍生成 ORDER BY');
$descSql = DB::table('tmp_reg_auto')->order('id', 'desc')->fetchSql()->select();
check(str_contains($descSql, 'DESC'), '合法 desc 方向保留');
$badSql = DB::table('tmp_reg_auto')->order('id', 'random_direction')->fetchSql()->select();
check(!str_contains($badSql, 'random_direction'), '非法方向归 ASC（不原样拼接）');

// ---------- P0-2：Raw/Closure 子查询 bind 合并 ----------
echo "\n== P0-2 Raw/Closure bind 合并 ==\n";

$rawRow = DB::table('tmp_reg_auto')->where('name', '=', new Raw('?', ['批量1']))->find();
check(is_array($rawRow) && ($rawRow['name'] ?? '') === '批量1', 'where Raw 自带 bind 正确执行');

$subCount = DB::table('tmp_reg_auto')->where('id', '=', function ($q) {
    $q->table('tmp_reg_auto')->where('name', '=', '批量2')->field('id')->limit(1);
})->count();
check($subCount >= 1, 'parseCompare Closure 子查询 bind 正确合并 (= 子查询)');

$existsCount = DB::table('tmp_reg_auto')->whereExists(function ($q) {
    $q->table('tmp_reg_auto')->where('name', '=', '批量3');
})->count();
check($existsCount >= 1, 'whereExists Closure bind 正确合并');

// ---------- P0-3：strtr 防 token 碰撞 ----------
echo "\n== P0-3 strtr 防 token 碰撞 ==\n";

// Raw 值中含 %ORDER% 字面量：str_replace 会被二次替换成 ORDER BY 片段，strtr 单遍替换保留
$tokenSql = DB::table('tmp_reg_auto')->where('name', '=', new Raw('"%ORDER%"'))->fetchSql()->select();
check(str_contains($tokenSql, '"%ORDER%"'), 'Raw 值中的 %ORDER% 字面量未被二次替换');

// ---------- IdType 变体 ----------
echo "\n== IdType 变体 ==\n";

$snow = new RegSnowModel();
$snow->name = '雪花';
$snow->save();
check($snow->id > 0, 'ASSIGN_ID 生成雪花 ID (>0)');

$uuid = new RegUuidModel();
$uuid->name = 'UUID';
$uuid->save();
check(strlen($uuid->id) === 36, 'ASSIGN_UUID 生成 UUID (36 字符)');
check(str_contains($uuid->id, '-'), 'UUID 含连字符');

// ---------- Db 层独立使用 ----------
echo "\n== Db 层独立使用 ==\n";

$row = DB::table('tmp_reg_auto')->limit(1)->find();
check(is_array($row), 'Db 层 find 返回数组');
$list = DB::table('tmp_reg_auto')->limit(2)->select();
check(count($list) === 2, 'Db 层 select 返回数组列表');
$insId = DB::table('tmp_reg_auto')->insert(['name' => 'Db层插入', 'status' => 9]);
check($insId > 0, 'Db 层 insert 返回自增 ID');
check(DB::table('tmp_reg_auto')->where('name', '=', 'Db层插入')->count() === 1, 'Db 层插入生效');

// ---------- forceDelete 物理删除 ----------
echo "\n== forceDelete ==\n";

$fd = RegAutoModel::where('id', '=', $m2->id)->find();
$fd->forceDelete();
check(DB::table('tmp_reg_auto')->where('id', '=', $m2->id)->count() === 0, 'forceDelete 物理删除');

// ---------- 事务 ----------
echo "\n== 事务 ==\n";

$txThrown = false;
try {
    DB::transaction(function () {
        DB::table('tmp_reg_auto')->insert(['name' => '事务内', 'status' => 10]);
        throw new RuntimeException('回滚测试');
    });
} catch (RuntimeException $e) {
    $txThrown = $e->getMessage() === '回滚测试';
}
check($txThrown, '事务闭包异常向上传播');
check(DB::table('tmp_reg_auto')->where('name', '=', '事务内')->count() === 0, '事务已回滚');

echo "\n";
check_summary('核心回归验证');