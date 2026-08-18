<?php
// +----------------------------------------------------------------------
// | with('a.b') 嵌套预加载验证（SQLite :memory: 无外部依赖）
// | 验证点：
// |   1. with('comments.author') 恰好 3 条 SQL（无 N+1）
// |   2. 嵌套数据正确性（每条 comment 的 author 正确加载）
// |   3. with('comments', 'comments.author') 去重（仍 3 条 SQL）
// |   4. 无嵌套 with('comments') 为 2 条 SQL（基线）
// +----------------------------------------------------------------------

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Model;

// ===================== 测试模型 =====================

#[Table('post')]
class EagerPost extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $title = '';

    #[HasMany(EagerComment::class, 'post_id', 'id')]
    public array $comments = [];
}

#[Table('comment')]
class EagerComment extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $postId = 0;

    public int $authorId = 0;

    #[HasOne(EagerAuthor::class, 'id', 'author_id')]
    public ?EagerAuthor $author = null;
}

#[Table('author')]
class EagerAuthor extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';
}

// ===================== 断言工具 =====================

$GLOBALS['sqlCount'] = 0;
$GLOBALS['failures'] = 0;

function check(bool $cond, string $label): void
{
    if ($cond) {
        echo "  PASS: {$label}\n";
    } else {
        echo "  FAIL: {$label}\n";
        $GLOBALS['failures']++;
    }
}

// ===================== 初始化 =====================

DB::setConfig([
    'default'     => 'sqlite',
    'connections' => [
        'sqlite' => [
            'type'     => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ],
    ],
]);

// SQL 计数监听器（同时验证 trigger_sql 门控在真实查询下工作）
DB::listen('sql', function (string $sql): void {
    $GLOBALS['sqlCount']++;
});

$conn = DB::connect();

$conn->execute('CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)');
$conn->execute('CREATE TABLE comment (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, author_id INTEGER)');
$conn->execute('CREATE TABLE author (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

$conn->execute("INSERT INTO author (name) VALUES ('Alice'), ('Bob'), ('Carol')");
$conn->execute("INSERT INTO post (title) VALUES ('p1'), ('p2'), ('p3')");
// p1: 2 条评论（Alice、Bob）；p2: 1 条评论（Bob）；p3: 无评论
$conn->execute("INSERT INTO comment (post_id, author_id) VALUES (1, 1), (1, 2), (2, 2)");

echo "\n== 测试 1: with('comments.author') 嵌套预加载 ==\n";

$GLOBALS['sqlCount'] = 0;
$posts = EagerPost::with('comments.author')->select();

check($GLOBALS['sqlCount'] === 3, 'SQL 恰好 3 条（post/comment/author 各一，无 N+1），实际 ' . $GLOBALS['sqlCount']);

check(count($posts) === 3, '3 篇 post');
check(count($posts[0]->comments) === 2, 'p1 有 2 条评论');
check(count($posts[1]->comments) === 1, 'p2 有 1 条评论');
check(count($posts[2]->comments) === 0, 'p3 无评论');
check($posts[0]->comments[0]->author?->name === 'Alice', 'p1 评论1 作者 Alice');
check($posts[0]->comments[1]->author?->name === 'Bob', 'p1 评论2 作者 Bob');
check($posts[1]->comments[0]->author?->name === 'Bob', 'p2 评论作者 Bob');

echo "\n== 测试 2: with('comments', 'comments.author') 去重 ==\n";

$GLOBALS['sqlCount'] = 0;
$posts2 = EagerPost::with('comments', 'comments.author')->select();

check($GLOBALS['sqlCount'] === 3, 'SQL 仍恰好 3 条（comments 不重复加载），实际 ' . $GLOBALS['sqlCount']);
check(count($posts2[0]->comments) === 2 && $posts2[0]->comments[0]->author?->name === 'Alice', '数据仍正确');

echo "\n== 测试 3: 基线 with('comments') 无嵌套 ==\n";

$GLOBALS['sqlCount'] = 0;
$posts3 = EagerPost::with('comments')->select();

check($GLOBALS['sqlCount'] === 2, 'SQL 2 条（post/comment），实际 ' . $GLOBALS['sqlCount']);
check(count($posts3[0]->comments) === 2, 'p1 评论仍 2 条');
check($posts3[0]->comments[0]->author === null, 'author 未加载（null）');

echo "\n== 测试 4: with('comments.author.missing') 未知深层关系静默跳过 ==\n";

$GLOBALS['sqlCount'] = 0;
$posts4 = EagerPost::with('comments.author.missing')->select();

// comments → author 正常递归，author 层 'missing' 未知 → 该层不查
check($GLOBALS['sqlCount'] === 3, 'SQL 3 条（post/comment/author，未知层跳过），实际 ' . $GLOBALS['sqlCount']);
check($posts4[0]->comments[0]->author?->name === 'Alice', 'author 仍正确加载');

echo "\n" . ($GLOBALS['failures'] === 0 ? 'ALL PASS' : $GLOBALS['failures'] . ' FAILURES') . "\n";
exit($GLOBALS['failures'] === 0 ? 0 : 1);
