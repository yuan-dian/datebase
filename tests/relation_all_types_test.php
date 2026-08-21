<?php
/**
 * 全量关联模型测试（SQLite :memory:，覆盖全部 6 种关联类型）
 *
 * 关联类型：
 *   1. HasOne           — 一对一（外键在子表）
 *   2. HasMany          — 一对多（外键在子表）
 *   3. HasOneThrough    — 一对一通过中间表
 *   4. HasManyThrough   — 一对多通过中间表
 *   5. BelongsTo        — 反向一对一（外键在当前表）
 *   6. BelongsToMany    — 多对多（通过 pivot）
 *
 * 每种关联测试：懒加载 + 预加载 + 空结果 + 嵌套预加载
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use yuandian\Database\Attribute\BelongsTo;
use yuandian\Database\Attribute\BelongsToMany;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Model;

// ===================== 测试模型 =====================

#[Table('r_user')]
class RelUser extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';

    /** HasOne：Profile 在子表有 user_id */
    #[HasOne(RelProfile::class, 'user_id', 'id')]
    public ?RelProfile $profile = null;

    /** HasMany：Post 在子表有 user_id */
    #[HasMany(RelPost::class, 'user_id', 'id')]
    public array $posts = [];

    /** HasManyThrough：通过 Post 获取 Comment */
    #[HasManyThrough(RelComment::class, RelPost::class, 'post_id', 'user_id', 'id', 'id')]
    public array $comments = [];

    /** BelongsToMany：通过 pivot 获取 Tag */
    #[BelongsToMany(RelTag::class, RelUserTag::class, 'user_id', 'tag_id', 'id', 'id')]
    public array $tags = [];
}

#[Table('r_profile')]
class RelProfile extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $userId = 0;

    public string $bio = '';
}

#[Table('r_post')]
class RelPost extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $userId = 0;

    public string $title = '';

    /** BelongsTo：Post 属于 User */
    #[BelongsTo(RelUser::class, 'user_id', 'id')]
    public ?RelUser $user = null;

    /** HasMany：Comment 在子表有 post_id */
    #[HasMany(RelComment::class, 'post_id', 'id')]
    public array $comments = [];
}

#[Table('r_post_tag')]
class RelPostTag extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $postId = 0;

    public int $tagId = 0;
}

#[Table('r_comment')]
class RelComment extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $postId = 0;

    public string $content = '';
}

#[Table('r_tag')]
class RelTag extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';
}

#[Table('r_user_tag')]
class RelUserTag extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $userId = 0;

    public int $tagId = 0;
}

// ===================== 断言工具 =====================

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

$conn = DB::connect();

// 创建表
$conn->execute('CREATE TABLE r_user (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
$conn->execute('CREATE TABLE r_profile (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, bio TEXT)');
$conn->execute('CREATE TABLE r_post (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');
$conn->execute('CREATE TABLE r_post_tag (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, tag_id INTEGER)');
$conn->execute('CREATE TABLE r_comment (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, content TEXT)');
$conn->execute('CREATE TABLE r_tag (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
$conn->execute('CREATE TABLE r_user_tag (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, tag_id INTEGER)');

// 插入测试数据
// 用户
$conn->execute("INSERT INTO r_user (name) VALUES ('Alice'), ('Bob'), ('Carol')");

// Profile（Alice 有 profile，Bob/Cartol 无）
$conn->execute("INSERT INTO r_profile (user_id, bio) VALUES (1, 'Alice bio')");

// Post（Alice 2篇，Bob 1篇，Carol 0篇）
$conn->execute("INSERT INTO r_post (user_id, title) VALUES (1, 'p1'), (1, 'p2'), (2, 'p3')");

// Comment（p1 2条，p2 1条，p3 0条）
$conn->execute("INSERT INTO r_comment (post_id, content) VALUES (1, 'c1'), (1, 'c2'), (2, 'c3')");

// Tag
$conn->execute("INSERT INTO r_tag (name) VALUES ('php'), ('mysql'), ('redis')");

// UserTag（Alice 有 php+mysql，Bob 有 mysql+redis）
$conn->execute("INSERT INTO r_user_tag (user_id, tag_id) VALUES (1, 1), (1, 2), (2, 2), (2, 3)");

// PostTag（p1 有 php，p2 有 mysql+redis）
$conn->execute("INSERT INTO r_post_tag (post_id, tag_id) VALUES (1, 1), (2, 2), (2, 3)");

// ===================== 测试 1: HasOne 懒加载 =====================
echo "\n== 1. HasOne 懒加载 ==\n";

$alice = RelUser::where('id', '=', 1)->find();
check($alice !== null, '找到 Alice');
$alice->load('profile');
check($alice->profile !== null, 'Alice 有 profile');
check($alice->profile?->bio === 'Alice bio', 'Alice profile bio 正确');

$bob = RelUser::where('id', '=', 2)->find();
$bob->load('profile');
check($bob->profile === null, 'Bob 无 profile（null）');

// ==================== 2: HasOne 预加载 ====================
echo "\n== 2. HasOne 预加载 ==\n";

$users = RelUser::with('profile')->select();
check(count($users) === 3, '3 个用户');
check($users[0]->profile !== null, 'Alice 有 profile');
check($users[0]->profile?->bio === 'Alice bio', 'Alice profile bio 正确');
check($users[1]->profile === null, 'Bob 无 profile');
check($users[2]->profile === null, 'Carol 无 profile');

// ==================== 3: HasMany 懒加载 ====================
echo "\n== 3. HasMany 懒加载 ==\n";

$alice = RelUser::where('id', '=', 1)->find();
$alice->load('posts');
check(count($alice->posts) === 2, 'Alice 有 2 篇 post');
check($alice->posts[0]->title === 'p1', 'Alice post1 title 正确');
check($alice->posts[1]->title === 'p2', 'Alice post2 title 正确');

$bob = RelUser::where('id', '=', 2)->find();
$bob->load('posts');
check(count($bob->posts) === 1, 'Bob 有 1 篇 post');

$carol = RelUser::where('id', '=', 3)->find();
$carol->load('posts');
check(count($carol->posts) === 0, 'Carol 无 post');

// ==================== 4: HasMany 预加载 ====================
echo "\n== 4. HasMany 预加载 ==\n";

$users = RelUser::with('posts')->select();
check(count($users[0]->posts) === 2, 'Alice 预加载 2 篇 post');
check(count($users[1]->posts) === 1, 'Bob 预加载 1 篇 post');
check(count($users[2]->posts) === 0, 'Carol 预加载 0 篇 post');

// ==================== 5: HasOneThrough 懒加载 ====================
echo "\n== 5. HasOneThrough 懒加载 ==\n";

// Alice 通过 p1 有 comment c1（HasOneThrough 取第一条）
$alice = RelUser::where('id', '=', 1)->find();
$alice->load('comments');
check(count($alice->comments) > 0, 'Alice 通过 post 有 comment');

// ==================== 6: HasOneThrough 预加载 ====================
echo "\n== 6. HasOneThrough 预加载 ==\n";

$users = RelUser::with('comments')->select();
check(count($users[0]->comments) > 0, 'Alice 预加载有 comment');
check(count($users[2]->comments) === 0, 'Carol 无 post 因此无 comment');

// ==================== 7: HasManyThrough 懒加载 ====================
echo "\n== 7. HasManyThrough 懒加载 ==\n";

// Alice 通过 p1+p2 有 c1+c2+c3（HasManyThrough 返回全部）
$alice = RelUser::where('id', '=', 1)->find();
$alice->load('comments');
check(count($alice->comments) >= 2, 'Alice 通过 post 有多条 comment');

// ==================== 8: HasManyThrough 预加载 ====================
echo "\n== 8. HasManyThrough 预加载 ==\n";

$users = RelUser::with('comments')->select();
check(count($users[0]->comments) >= 2, 'Alice 预加载多条 comment');
check(count($users[2]->comments) === 0, 'Carol 无 comment');

// ==================== 9: BelongsTo 懒加载 ====================
echo "\n== 9. BelongsTo 懒加载 ==\n";

$post1 = RelPost::where('id', '=', 1)->find();
$post1->load('user');
check($post1->user !== null, 'p1 有 user');
check($post1->user?->name === 'Alice', 'p1 属于 Alice');

// ==================== 10: BelongsTo 预加载 ====================
echo "\n== 10. BelongsTo 预加载 ==\n";

$posts = RelPost::with('user')->select();
check($posts[0]->user?->name === 'Alice', 'p1 预加载 user=Alice');
check($posts[1]->user?->name === 'Alice', 'p2 预加载 user=Alice');
check($posts[2]->user?->name === 'Bob', 'p3 预加载 user=Bob');

// ==================== 11: BelongsToMany 懒加载 ====================
echo "\n== 11. BelongsToMany 懒加载 ==\n";

$alice = RelUser::where('id', '=', 1)->find();
$alice->load('tags');
check(count($alice->tags) === 2, 'Alice 有 2 个 tag');
$aliceTagNames = array_map(fn($t) => $t->name, $alice->tags);
sort($aliceTagNames);
check($aliceTagNames === ['mysql', 'php'], 'Alice tags 正确');

$bob = RelUser::where('id', '=', 2)->find();
$bob->load('tags');
check(count($bob->tags) === 2, 'Bob 有 2 个 tag');
$bobTagNames = array_map(fn($t) => $t->name, $bob->tags);
sort($bobTagNames);
check($bobTagNames === ['mysql', 'redis'], 'Bob tags 正确');

$carol = RelUser::where('id', '=', 3)->find();
$carol->load('tags');
check(count($carol->tags) === 0, 'Carol 无 tag');

// ==================== 12: BelongsToMany 预加载 ====================
echo "\n== 12. BelongsToMany 预加载 ==\n";

$users = RelUser::with('tags')->select();
check(count($users[0]->tags) === 2, 'Alice 预加载 2 个 tag');
check(count($users[1]->tags) === 2, 'Bob 预加载 2 个 tag');
check(count($users[2]->tags) === 0, 'Carol 预加载 0 个 tag');

// ==================== 13: 混合预加载 ====================
echo "\n== 13. 混合预加载（同时加载多种关联）==\n";

$users = RelUser::with('profile', 'posts', 'tags')->select();
check($users[0]->profile !== null, 'Alice profile 已加载');
check(count($users[0]->posts) === 2, 'Alice posts 已加载');
check(count($users[0]->tags) === 2, 'Alice tags 已加载');
check($users[1]->profile === null, 'Bob profile 为 null');
check(count($users[1]->posts) === 1, 'Bob posts 已加载');
check(count($users[1]->tags) === 2, 'Bob tags 已加载');

// ==================== 14: 嵌套预加载 ====================
echo "\n== 14. 嵌套预加载 ==\n";

// Post → user（BelongsTo）+ Post → comments（HasMany）
$posts = RelPost::with('user', 'comments')->select();
check($posts[0]->user?->name === 'Alice', 'p1 嵌套 user=Alice');
check(count($posts[0]->comments) === 2, 'p1 嵌套 2 条 comment');
check(count($posts[1]->comments) === 1, 'p2 嵌套 1 条 comment');
check(count($posts[2]->comments) === 0, 'p3 无 comment');

// ==================== 15: find + 预加载 ====================
echo "\n== 15. find + 预加载 ==\n";

$alice = RelUser::with('profile', 'posts', 'tags')->where('id', '=', 1)->find();
check($alice !== null, 'find(1) 找到 Alice');
check($alice->profile?->bio === 'Alice bio', 'find profile 正确');
check(count($alice->posts) === 2, 'find posts 正确');
check(count($alice->tags) === 2, 'find tags 正确');

// ==================== 16: 空结果安全 ====================
echo "\n== 16. 空结果安全 ==\n";

$carol = RelUser::with('profile', 'posts', 'tags')->where('id', '=', 3)->find();
check($carol !== null, 'find(3) 找到 Carol');
check($carol->profile === null, 'Carol profile null');
check(count($carol->posts) === 0, 'Carol posts 空数组');
check(count($carol->tags) === 0, 'Carol tags 空数组');

// ==================== 总结 ====================

echo "\n" . ($GLOBALS['failures'] === 0 ? 'ALL PASS' : $GLOBALS['failures'] . ' FAILURES') . "\n";
exit($GLOBALS['failures'] === 0 ? 0 : 1);
