<?php

declare(strict_types=1);

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\Command;
use yuandian\Database\Db\Builder\Mongo as MongoBuilder;
use yuandian\Database\Db\Connector\Mongo as MongoConnection;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\model\MongoSoftDeleteModel;
use yuandian\Database\Tests\model\MongoTestModel;

require __DIR__ . '/../vendor/autoload.php';

/**
 * MongoDB 手动测试脚本
 *
 * 运行（需先加载 mongodb 扩展，本机未编译进 PHP 时用 -d extension 指向 .so）：
 *   php -d extension=/tmp/opencode/mongodb-2.3.3/modules/mongodb.so tests/mongo_test.php
 */

// ======================== 配置 ========================
const MONGO_HOST = '127.0.0.1';
const MONGO_PORT = '27017';
const MONGO_DB   = 'database';
const MONGO_USER = 'REMOVED';
const MONGO_PASS = 'REMOVED';
const MONGO_COLL = 'mongo_test';

DB::setConfig([
    'default'     => 'mongodb',
    'connections' => [
        'mongodb' => [
            'type'           => 'mongodb',
            'hostname'       => MONGO_HOST,
            'database'       => MONGO_DB,
            'username'       => MONGO_USER,
            'password'       => MONGO_PASS,
            'hostport'       => MONGO_PORT,
            'auth_source'    => 'admin',
            'dsn'            => '',
            'params'         => [],
            'charset'        => 'utf8',
            'pk'             => '_id',
            'pk_type'        => 'int',
            'prefix'         => '',
            'is_replica_set' => false,
            'type_map'       => 'array',
            'pk_convert_id'  => true,
            'trigger_sql'    => true,
        ],
    ],
]);

// ======================== 断言工具 ========================
$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$name}" . ($detail !== '' ? "  [{$detail}]" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "  FAIL  {$name}" . ($detail !== '' ? "  [{$detail}]" : '') . PHP_EOL;
    }
}

function section(string $title): void
{
    echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
    echo "  {$title}" . PHP_EOL;
    echo str_repeat('=', 60) . PHP_EOL;
}

/** 删除测试集合（不存在时静默忽略） */
function dropCollection(MongoConnection $conn): void
{
    try {
        $conn->getMongo()->executeCommand(MONGO_DB, new Command(['drop' => MONGO_COLL]));
    } catch (Throwable $e) {
        // 集合不存在或权限不足，忽略
    }
}

// ======================== 1. 连接 ========================
section('1. 连接建立');
try {
    $conn = DB::connect('mongodb');
    check('DB::connect 返回 Mongo 连接', $conn instanceof MongoConnection, get_class($conn));
    check('builder 为 MongoBuilder', $conn->getBuilder() instanceof MongoBuilder, get_class($conn->getBuilder()));

    // 触发惰性连接建立（getMongo 在 initConnect 前为 null）
    DB::table(MONGO_COLL)->find();

    $ping = $conn->getMongo()->executeCommand('admin', new Command(['ping' => 1]));
    $first = (array)($ping->toArray()[0] ?? []);
    check('ping 成功', ($first['ok'] ?? 0) == 1, json_encode($first));
} catch (Throwable $e) {
    check('连接建立', false, $e->getMessage());
    echo PHP_EOL . "连接失败，中止。{$pass} 通过 / {$fail} 失败" . PHP_EOL;
    exit(1);
}

// ======================== 2. 查询器层 CRUD ========================
section('2. 查询器层 CRUD（DB::table → MongoQuery）');
dropCollection($conn);

$id = null;
try {
    $id = DB::table(MONGO_COLL)->insert(['id' => 1001, 'name' => 'alpha', 'status' => 1, 'create_time' => '2026-08-18 10:00:00']);
    check('insert 单条返回字符串 ID', is_string($id) && $id !== '', (string)$id);

    $row = DB::table(MONGO_COLL)->where('id', '=', 1001)->find();
    check('find by id（pk_convert_id 转 _id）命中', is_array($row) && ($row['name'] ?? '') === 'alpha', json_encode($row));

    $rowRaw = DB::table(MONGO_COLL)->where('_id', '=', 1001)->find();
    check('find by _id 直查命中', is_array($rowRaw) && ($rowRaw['name'] ?? '') === 'alpha');

    $affected = DB::table(MONGO_COLL)->insertAll([
        ['id' => 1002, 'name' => 'beta',  'status' => 1],
        ['id' => 1003, 'name' => 'gamma', 'status' => 2],
        ['id' => 1004, 'name' => 'delta', 'status' => 2],
    ]);
    check('insertAll 3 条', $affected === 3, "affected={$affected}");

    $count = DB::table(MONGO_COLL)->count();
    check('count = 4', $count === 4, "count={$count}");

    $rows = DB::table(MONGO_COLL)->where('status', '=', 2)->select();
    check('select where status=2 命中 2 条', count($rows) === 2, 'count=' . count($rows));

    $updated = DB::table(MONGO_COLL)->where('status', '=', 2)->update(['status' => 3]);
    check('update 批量 2 条', $updated === 2, "modified={$updated}");

    $count3 = DB::table(MONGO_COLL)->where('status', '=', 3)->count();
    check('update 后 count(status=3) = 2', $count3 === 2, "count={$count3}");

    $iterated = 0;
    foreach (DB::table(MONGO_COLL)->where('status', '>', 0)->cursor() as $cRow) {
        $iterated++;
    }
    check('cursor 迭代 4 行', $iterated === 4, "rows={$iterated}");

    $deleted = DB::table(MONGO_COLL)->where('name', '=', 'delta')->delete();
    check('delete 按条件 1 条', $deleted === 1, "deleted={$deleted}");

    $finalCount = DB::table(MONGO_COLL)->count();
    check('删除后 count = 3', $finalCount === 3, "count={$finalCount}");
} catch (Throwable $e) {
    check('查询器层 CRUD', false, get_class($e) . ': ' . $e->getMessage());
}

// ======================== 3. 模型层 CRUD ========================
section('3. 模型层 CRUD（MongoTestModel → MongoModelQuery）');
dropCollection($conn);

$modelId = 0;
try {
    $m = new MongoTestModel();
    $m->name = 'model_alpha';
    $m->status = 1;
    $m->remark = 'first';
    $m->createTime = '2026-08-18 11:00:00';
    $saved = $m->save();
    check('save 成功', $saved === true);
    check('save 后 exists', $m->exists() === true);
    check('save 后 id 已预置（ASSIGN_ID）', $m->id > 0, "id={$m->id}");
    $modelId = $m->id;

    $found = MongoTestModel::where('id', '=', $modelId)->find();
    check('find 返回模型', $found instanceof MongoTestModel);
    check('find 字段匹配', $found !== null && $found->name === 'model_alpha', $found ? $found->name : 'null');
    check('find 主键水合', $found !== null && $found->id === $modelId, $found ? (string)$found->id : 'null');
    check('find remark 水合', $found !== null && $found->remark === 'first', $found ? (string)$found->remark : 'null');
    check('find create_time 水合', $found !== null && $found->createTime === '2026-08-18 11:00:00', $found ? $found->createTime : 'null');

    $m2 = new MongoTestModel();
    $m2->name = 'model_beta';
    $m2->status = 1;
    $m2->save();

    $m3 = new MongoTestModel();
    $m3->name = 'model_gamma';
    $m3->status = 2;
    $m3->save();

    $list = MongoTestModel::where('status', '=', 1)->select();
    check('select 命中 2 条模型', count($list) === 2, 'count=' . count($list));
    check('select 元素为模型实例', !empty($list) && ($list[0] ?? null) instanceof MongoTestModel);

    $first = $list[0] ?? null;
    $first->name = 'model_alpha_updated';
    $updated = $first->update();
    check('模型 update 成功', $updated === true);

    $reloaded = MongoTestModel::where('id', '=', $first->id)->find();
    check('update 已持久化', $reloaded !== null && $reloaded->name === 'model_alpha_updated', $reloaded ? $reloaded->name : 'null');

    $toArr = $first->toArray();
    check('toArray 含更新字段', ($toArr['name'] ?? '') === 'model_alpha_updated');

    $deleted = $first->delete();
    check('模型 delete 成功', $deleted === true);
    check('delete 后 find 为空', MongoTestModel::where('id', '=', $first->id)->find() === null);

    $remain = MongoTestModel::count();
    check('剩余 2 条', $remain === 2, "count={$remain}");
} catch (Throwable $e) {
    check('模型层 CRUD', false, get_class($e) . ': ' . $e->getMessage());
}

// ======================== 3.5 limit/skip 语义统一（limit(offset,length) → limit(limit)+skip） ========================
section('3.5 limit/skip 语义统一');
dropCollection($conn);

try {
    for ($i = 1; $i <= 5; $i++) {
        $m = new MongoTestModel();
        $m->name = "limit_{$i}";
        $m->status = 1;
        $m->save();
    }

    // limit(2)：单参 = 取前 2 条（修复前 Mongo 语义为从第 2 条开始）
    $top2 = MongoTestModel::order('name', 'asc')->limit(2)->select();
    check('limit(2) 取前 2 条', count($top2) === 2 && ($top2[0]->name ?? '') === 'limit_1', 'count=' . count($top2) . ', first=' . ($top2[0]->name ?? 'null'));

    // limit(2) 与 PDO 侧语义一致：不跳过首条（修复前 skip=2）
    $all = MongoTestModel::order('name', 'asc')->select();
    check('limit(2) 首条 = 全量首条', $top2[0]->id === $all[0]->id, 'limitFirst=' . $top2[0]->id . ', allFirst=' . $all[0]->id);

    // skip(2)->limit(2)：偏移分页（显式 skip 替代旧双参）
    $page2 = MongoTestModel::order('name', 'asc')->skip(2)->limit(2)->select();
    check('skip(2)->limit(2) 取第 3-4 条', count($page2) === 2 && ($page2[0]->name ?? '') === 'limit_3', 'count=' . count($page2) . ', first=' . ($page2[0]->name ?? 'null'));

    // offset(2)->limit(2)：Db 层通用 offset 映射 skip（parseOptions 路径）
    $page2b = MongoTestModel::order('name', 'asc')->offset(2)->limit(2)->select();
    check('offset(2)->limit(2) 等价 skip', count($page2b) === 2 && $page2b[0]->id === $page2[0]->id, 'count=' . count($page2b));

    // count + skip/limit 组合
    $countPage2 = MongoTestModel::skip(2)->limit(2)->count();
    check('count 吃 skip/limit', $countPage2 === 2, "count={$countPage2}");
} catch (Throwable $e) {
    check('limit/skip 语义统一', false, get_class($e) . ': ' . $e->getMessage());
}

// ======================== 4. 模型层 chunk（with 预加载 + offset 分页） ========================
section('4. 模型层 chunk（with 预加载 + offset 分页）');
dropCollection($conn);

try {
    // 数据：3 父 + 2 子（父 id 已知，子 parentId 指向父）
    $parents = [];
    for ($i = 1; $i <= 3; $i++) {
        $p = new MongoTestModel();
        $p->name = "chunk_parent_{$i}";
        $p->status = 1;
        $p->save();
        $parents[] = $p->id;
    }
    $c1 = new MongoTestModel();
    $c1->name = 'chunk_child_1';
    $c1->status = 1;
    $c1->parentId = $parents[0];
    $c1->save();
    $c2 = new MongoTestModel();
    $c2->name = 'chunk_child_2';
    $c2->status = 1;
    $c2->parentId = $parents[0];
    $c2->save();

    // R2 验证：with('children')->chunk() 每块应携带预加载（修复前 copyExtraState 缺失，children 为空数组）
    $r2Loaded = false;
    MongoTestModel::with('children')->chunk(10, function (array $items) use (&$r2Loaded): bool {
        foreach ($items as $item) {
            if (!empty($item->children)) {
                $r2Loaded = true;
            }
        }
        return true;
    });
    check('with(children)->chunk 预加载生效', $r2Loaded === true);

    $r2Count = 0;
    MongoTestModel::with('children')->chunk(10, function (array $items) use (&$r2Count): bool {
        $r2Count = max($r2Count, count($items[0]->children ?? []));
        return true;
    });
    check('首块 children 数量正确', $r2Count === 2, "children={$r2Count}");

    // N6 验证：order->chunk(2) 应分 3 页取完 5 条（修复前 offset 忽略→每页同数据→死循环）
    $pages = 0;
    $total = 0;
    $seen = [];
    MongoTestModel::order('id', 'asc')->chunk(2, function (array $items) use (&$pages, &$total, &$seen): bool {
        $pages++;
        $total += count($items);
        foreach ($items as $item) {
            $seen[$item->id] = true;
        }
        return $pages < 20; // 防死循环保护：修复前会无限分页
    });
    check('chunk 分页页数正确（修复前死循环至 20 页保护）', $pages === 3, "pages={$pages}");
    check('chunk 累计条数正确（修复前每页重复同 2 条）', $total === 5, "total={$total}");
    check('chunk 无重复记录（修复前同页数据重复）', count($seen) === 5, 'seen=' . count($seen));
} catch (Throwable $e) {
    check('模型层 chunk', false, get_class($e) . ': ' . $e->getMessage());
}

// ======================== 4.1 模型层 order（数组签名 + camelCase 字段） ========================

try {
    // B2 验证：order() 数组签名（修复前 string 签名下数组传参必 TypeError）
    $orderArr = MongoTestModel::order(['id' => 'asc'])->select();
    $idsArr = array_map(fn ($m) => $m->id, $orderArr);
    $sortedIds = $idsArr;
    sort($sortedIds);
    check('order 数组签名可用', count($idsArr) === 5, 'count=' . count($idsArr));
    check('order 数组签名按 id 升序', $idsArr === $sortedIds, 'ids=' . implode(',', $idsArr));

    // B3 验证：camelCase 字段经 convertFieldName 转 snake_case（修复前按不存在字段排序）
    $orderCamel = MongoTestModel::order('parentId', 'desc')->select();
    check('order camelCase 字段不抛错', count($orderCamel) === 5, 'count=' . count($orderCamel));
} catch (Throwable $e) {
    check('模型层 order 增强', false, get_class($e) . ': ' . $e->getMessage());
}

// ======================== 5. 清理 ========================
section('5. 清理');
dropCollection($conn);

// ======================== 6. 模型层软删除（N7） ========================
section('6. 模型层软删除（MongoSoftDeleteModel → N7 接线验证）');

try {
    $conn->getMongo()->executeCommand(MONGO_DB, new Command(['drop' => 'mongo_test_soft']));
} catch (Throwable $e) {
    // 集合不存在，忽略
}

try {
    $rows = [];
    for ($i = 1; $i <= 3; $i++) {
        $m = new MongoSoftDeleteModel();
        $m->name = "soft_{$i}";
        $m->status = $i;
        $m->save();
        $rows[] = $m->id;
    }

    $countAll = MongoSoftDeleteModel::count();
    check('软删模型 count = 3（无过滤干扰）', $countAll === 3, "count={$countAll}");

    $first = MongoSoftDeleteModel::where('id', '=', $rows[0])->find();
    check('软删模型 find 命中', $first !== null && $first->name === 'soft_1');

    $deleted = $first->delete();
    check('软删 delete 返回 true', $deleted === true);
    check('软删后 find 为空（查询过滤生效）', MongoSoftDeleteModel::where('id', '=', $rows[0])->find() === null);
    check('软删后 select 过滤', count(MongoSoftDeleteModel::select()) === 2, 'count=' . count(MongoSoftDeleteModel::select()));
    check('软删后 count 过滤', MongoSoftDeleteModel::count() === 2, 'count=' . MongoSoftDeleteModel::count());

    // withoutGlobalScope('softDelete')：软删行可见（数据仍在，未物理删除）
    $rawAll = MongoSoftDeleteModel::withoutGlobalScope('softDelete')->count();
    check('withoutGlobalScope 可见全部 3 条（软删非物理删）', $rawAll === 3, "count={$rawAll}");

    // force()：物理删除，软删行也一并消失（查询器静态链）
    $secondId = $rows[1];
    MongoSoftDeleteModel::withoutGlobalScope('softDelete')->where('id', '=', $secondId)->force()->delete();
    check('force 物理删后原始视图 2 条', MongoSoftDeleteModel::withoutGlobalScope('softDelete')->count() === 2, 'count=' . MongoSoftDeleteModel::withoutGlobalScope('softDelete')->count());

    // Model::forceDelete() 实例便捷入口
    $third = MongoSoftDeleteModel::withoutGlobalScope('softDelete')->where('id', '=', $rows[2])->find();
    $third->forceDelete();
    check('forceDelete 后全部删除', MongoSoftDeleteModel::withoutGlobalScope('softDelete')->count() === 1, 'count=' . MongoSoftDeleteModel::withoutGlobalScope('softDelete')->count());

    // 底层确认：软删行存在但被过滤（deleted_time 已写入）
    $softRow = DB::table('mongo_test_soft')->where('id', '=', $rows[0])->find();
    check('软删行底层可见且 deleted_time 已写入', is_array($softRow) && !empty($softRow['deleted_time']), json_encode($softRow));

    // 清理
    try {
        $conn->getMongo()->executeCommand(MONGO_DB, new Command(['drop' => 'mongo_test_soft']));
    } catch (Throwable $e) {
    }
} catch (Throwable $e) {
    check('模型层软删除', false, get_class($e) . ': ' . $e->getMessage());
}

echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
echo "  结果：{$pass} 通过 / {$fail} 失败" . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;
exit($fail > 0 ? 1 : 0);
