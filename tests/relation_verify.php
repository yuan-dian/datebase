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
// 模型关联注解全面验证：HasOne / HasMany / HasOneThrough / HasManyThrough
// 覆盖：懒加载 load()、预加载 with()、自定义外键/本地键、空关联、toArray 序列化

declare(strict_types=1);

use yuandian\Database\Db\Raw;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\model\RelationResourceFile;
use yuandian\Database\Tests\model\RelationResourceFolder;
use yuandian\Database\Tests\model\RelationShareBase;
use yuandian\Database\Tests\model\RelationShareFile;
use yuandian\Database\Tests\model\RelationShareFolder;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

echo "开始关联注解验证...\n\n";

// ---------- 基准数据准备 ----------
// 取一张有文件的 share（share_file 中 share_id 出现次数最多）
// 聚合表达式不能被 parseKey 反引号包裹，故在 PHP 端取最大值
$shareCounts = DB::table('share_file')
    ->field([new Raw('share_id'), new Raw('COUNT(*) AS c')])
    ->groupBy('share_id')
    ->select();
usort($shareCounts, fn($a, $b) => $b['c'] <=> $a['c']);
$shareId = (int)($shareCounts[0]['share_id'] ?? 0);

// 取一张有文件夹的 share（share_id 存在于 share_base 且 folder_id 在 resource_folder 有对应）
$candidates = DB::table('share_folder')->field(['share_id', 'folder_id'])->select();
$validShares = DB::table('share_base')
    ->whereIn('share_id', array_column($candidates, 'share_id'))
    ->column('share_id');
$validFolders = DB::table('resource_folder')
    ->whereIn('folder_id', array_column($candidates, 'folder_id'))
    ->column('folder_id');
$folderShareId = 0;
$folderId = 0;
foreach ($candidates as $cand) {
    if (in_array($cand['share_id'], $validShares) && in_array($cand['folder_id'], $validFolders)) {
        $folderShareId = (int)$cand['share_id'];
        $folderId = (int)$cand['folder_id'];
        break;
    }
}

// share_file 中该 share 对应的 file_id 集合（HasManyThrough 基准）
$fileIds = DB::table('share_file')
    ->where('share_id', '=', $shareId)
    ->column('file_id');

echo "基准: share_id={$shareId} (files=" . count($fileIds) . "), folderShareId={$folderShareId} folderId={$folderId}\n\n";

// ---------- HasMany：懒加载 + 数据正确性 ----------
echo "== HasMany(share_base -> share_file by share_id) ==\n";

$share = RelationShareBase::where('share_id', '=', $shareId)->find();
check($share instanceof RelationShareBase, 'find() 返回模型实例');

$share->load('shareFiles');
$files = $share->shareFiles;
check(is_array($files), 'load(shareFiles) 返回数组');
check(count($files) === count($fileIds), '关联数量与 SQL 基准一致 (' . count($files) . '=' . count($fileIds) . ')');
check(count($files) > 0, '关联数据非空');
check($files[0] instanceof RelationShareFile, '元素为 RelationShareFile 模型');
check($files[0]->shareId === $shareId, '外键值正确 (shareId)');

// ---------- HasMany：预加载 with() ----------
echo "\n== HasMany 预加载 with(shareFiles) ==\n";

$list = RelationShareBase::with('shareFiles')->where('share_id', 'in', [$shareId])->select();
check(count($list) === 1, 'with 预加载 select 返回 1 条');
check(is_array($list[0]->shareFiles), '预加载后 shareFiles 为数组');
check(count($list[0]->shareFiles) === count($fileIds), '预加载数量与懒加载一致');

// ---------- HasOne：懒加载 ----------
echo "\n== HasOne(share_base -> share_folder by share_id) ==\n";

$folderShare = RelationShareBase::where('share_id', '=', $folderShareId)->find();
$folderShare->load('shareFolder');
check($folderShare->shareFolder instanceof RelationShareFolder, 'load(shareFolder) 返回 RelationShareFolder');
check($folderShare->shareFolder->folderId === $folderId, 'HasOne folderId 与基准一致');
check($folderShare->shareFolder->shareId === $folderShareId, 'HasOne shareId 回指正确');

// ---------- HasManyThrough：share_base -> share_file -> resource_file ----------
echo "\n== HasManyThrough(share_base -> resource_file via share_file) ==\n";

$share->load('resourceFiles');
$rfs = $share->resourceFiles;
check(is_array($rfs), 'load(resourceFiles) 返回数组');
check(count($rfs) === count($fileIds), 'Through 关联数量与 file_id 集合一致 (' . count($rfs) . '=' . count($fileIds) . ')');
check($rfs[0] instanceof RelationResourceFile, '元素为 RelationResourceFile 模型');

$gotFileIds = array_map(fn($rf) => $rf->fileId, $rfs);
$diff = array_diff($fileIds, $gotFileIds);
check(empty($diff), 'Through file_id 集合与 share_file 基准完全一致');
check($rfs[0]->fileName !== '', '关联目标字段非空 (fileName)');

// ---------- HasOneThrough：share_base -> share_folder -> resource_folder ----------
echo "\n== HasOneThrough(share_base -> resource_folder via share_folder) ==\n";

$folderShare->load('resourceFolder');
check($folderShare->resourceFolder instanceof RelationResourceFolder, 'load(resourceFolder) 返回 RelationResourceFolder');
check($folderShare->resourceFolder->folderId === $folderId, 'Through folderId 与基准一致');
check($folderShare->resourceFolder->folderName !== '', 'Through 目标字段非空 (folderName)');

// ---------- 空关联边界 ----------
echo "\n== 空关联边界 ==\n";

$empty = RelationShareBase::where('share_id', '=', 999999999999999999)->find();
check($empty === null, '不存在的 share 返回 null');

$noRelation = RelationShareBase::where('share_id', '=', $shareId)->find();
$noRelation->load('shareFolder');
check($noRelation->shareFolder === null, '无 folder 记录时 HasOne 返回 null（不再抛 Typed property 错误）');
check(property_exists($noRelation, 'shareFolder'), '属性仍存在');

// ---------- 多重关联同时加载 ----------
echo "\n== 多重关联 load() ==\n";

$multi = RelationShareBase::where('share_id', '=', $shareId)->find();
$multi->load('shareFiles', 'shareFolder');
check(is_array($multi->shareFiles), 'load 多关联 on 批: shareFiles');
check($multi->shareFolder === null || $multi->shareFolder instanceof RelationShareFolder, 'load 多关联 on 批: shareFolder');

// ---------- toArray 序列化 ----------
echo "\n== toArray / toJson ==\n";

$arr = $share->toArray();
check(isset($arr['share_id']), 'toArray 含主键 share_id');
check(array_key_exists('share_files', $arr) || array_key_exists('shareFiles', $arr), 'toArray 含关联键 (share_files 或 shareFiles)');
check(is_array($arr['share_files'] ?? $arr['shareFiles'] ?? null), 'toArray 关联值为数组');

$json = json_decode(json_encode($share), true);
check(is_array($json), 'json_encode 可反序列化');
check(isset($json['shareId']), 'json_encode 序列化 public 属性 (camelCase shareId)');

$str = (string)$share;
$strJson = json_decode($str, true);
check(isset($strJson['share_id']), 'toJson __toString 输出列名字段 (share_id)');

// ---------- Db 层独立使用对照 ----------
echo "\n== Db 层独立使用（重构对照） ==\n";

$dbRow = DB::table('share_base')->where('share_id', '=', $shareId)->find();
check(is_array($dbRow), 'Db 层 find 返回原生数组');
check(isset($dbRow['share_id']), 'Db 层数组键为列名 (share_id)');
check($dbRow['share_id'] == $shareId, 'Db 层值正确');

$dbFiles = DB::table('share_file')->where('share_id', '=', $shareId)->select();
check(is_array($dbFiles) && count($dbFiles) === count($fileIds), 'Db 层关联数据量一致');

// ---------- fetchSql 含关联 ----------
echo "\n== fetchSql + with ==\n";

$sql = RelationShareBase::with('shareFiles')->fetchSql()->select();
check(is_string($sql) && str_contains($sql, 'FROM'), 'fetchSql+with 返回 SQL 字符串');

echo "\n";
check_summary('关联注解验证');