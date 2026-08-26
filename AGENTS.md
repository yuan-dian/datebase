# yuandian/database

一个轻量级的 PHP 8.1+ ORM 库，支持注解驱动模型、多数据库（MySQL、SQLite、MongoDB、Oracle）、关联关系、软删除和雪花 ID。

## 特性

- 🚀 注解驱动的模型定义（PHP 8.1 Attributes）
- 🔌 多数据库支持：MySQL、SQLite、MongoDB、Oracle
- 🔗 关联关系：HasOne、HasMany、BelongsTo、BelongsToMany、HasOneThrough、HasManyThrough
- 🗑️ 软删除支持（支持自定义默认值与删除值）
- 🌐 全局作用域（自动注入查询约束）
- ⏰ 自动时间戳（create_time / update_time）
- 🎲 雪花 ID / UUID 生成
- 🔄 类型转换（#[Cast] 注解 + CasterRegistry）
- 📦 轻量级，无额外依赖

## 环境要求

- PHP >= 8.1
- ext-pdo（必需）
- ext-mongodb（可选，用于 MongoDB 支持）

## 安装

```bash
composer require yuandian/database
```

## 快速开始

### 配置

```php
use yuandian\Database\DbManager;
use yuandian\Database\Facades\DB;

DbManager::setConfig([
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'database' => 'your_database',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],
        'sqlite' => [
            'database' => ':memory:',
        ],
    ],
]);
```

### 定义模型

```php
use yuandian\Database\Model\Model;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Enums\IdType;

#[Table('users')]
#[SoftDelete]
#[AutoWriteTime]
class User extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';

    public string $email = '';

    public int $age = 0;
}
```

> 💡 **注意**：属性使用 camelCase 命名，自动转换为 snake_case 列名（`$testUserId` → `test_user_id`）

### CRUD 操作

```php
// 创建
$user = new User();
$user->name = '张三';
$user->email = 'zhangsan@example.com';
$user->age = 25;
$user->insert();

// 查询
$user = User::where('id', '=', 1)->find();
$users = User::where('age', '>', 18)->select();

// 更新
$user->age = 26;
$user->save();

// 删除（软删除）
$user->delete();

// 恢复
$user->restore();

// 强制删除
$user->force()->delete();
```

### 关联关系

```php
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\BelongsTo;
use yuandian\Database\Attribute\BelongsToMany;

class Post extends Model
{
    #[HasOne(Profile::class, 'user_id', 'id')]
    public ?Profile $profile = null;

    #[HasMany(Comment::class, 'post_id', 'id')]
    public ?array $comments = null;

    #[BelongsTo(User::class, 'user_id', 'id')]
    public ?User $user = null;

    #[BelongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id')]
    public ?array $tags = null;
}

// 预加载
$posts = Post::with(['user', 'comments'])->select();

// 懒加载
$post->load('comments');
```

### 查询构建器

```php
// 链式调用
$users = User::where('age', '>', 18)
    ->where('status', '=', 'active')
    ->order('id', 'DESC')
    ->limit(10)
    ->select();

// 聚合
$count = User::where('age', '>', 18)->count();
$avg = User::avg('age');

// 事务
DB::transaction(function () {
    $user = new User();
    $user->name = '新用户';
    $user->insert();
});
```

### 全局作用域

```php
use yuandian\Database\Scope\Scope;
use yuandian\Database\Db\BaseQuery;

// 定义作用域
class ActiveScope implements Scope
{
    public function apply(BaseQuery $query, string $modelClass): void
    {
        $query->where('status', '=', 'active');
    }
}

// 注册
class User extends Model
{
    protected static function registerScopes(): void
    {
        static::addGlobalScope(ActiveScope::class);
    }
}

// 查询时跳过
$users = User::withoutGlobalScope(ActiveScope::class)->select();
$users = User::withoutGlobalScopes()->select();
```

## 测试

```bash
# 安装依赖
composer install

# 运行测试
php vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap.php tests/SQLite/
```

## 项目结构

```
src/
├── Attribute/          # PHP 8.1 注解（Table, TableId, SoftDelete, HasOne 等）
├── Cast/               # 类型转换器（Caster 接口 + 内置实现）
├── Config/
│   └── database.php    # 默认配置
├── Db/
│   ├── Builder/        # SQL 构建器（MySQL, Oracle, SQLite, MongoDB）
│   ├── Connector/      # 数据库连接器
│   ├── BaseQuery.php   # 查询构建器
│   ├── Connection.php  # 抽象连接
│   ├── PDOConnection.php
│   └── MongoQuery.php  # MongoDB 查询
├── Enums/
│   └── IdType.php      # ID 类型：AUTO, ASSIGN_ID, ASSIGN_UUID
├── Exceptions/
├── Facade/
│   └── DB.php          # 静态门面
├── Model/
│   ├── Model.php       # 基础模型
│   └── Relations/      # 关联关系实现
├── Scope/
│   ├── Scope.php       # 全局作用域接口
│   └── SoftDeleteScope.php  # 内置软删作用域
└── DbManager.php       # 连接管理器
```

## 代码规范

- 所有文件使用 `declare(strict_types=1)`
- 属性使用 camelCase，自动转换为 snake_case 列名
- 使用 PHP 8.1 Attributes 定义元数据
- PSR-4 自动加载

## 贡献指南

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/amazing-feature`)
3. 提交更改 (`git commit -m 'feat: add amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 创建 Pull Request

### 提交规范

使用 [Conventional Commits](https://www.conventionalcommits.org/) 规范：

- `feat:` 新功能
- `fix:` 修复 bug
- `docs:` 文档更新
- `style:` 代码格式（不影响功能）
- `refactor:` 重构
- `perf:` 性能优化
- `test:` 测试相关
- `chore:` 构建/工具相关

## 许可证

MIT License

## 相关包

- [yuandian/container](https://github.com/yuandian/container) - 容器/门面系统
- [yuandian/tools](https://github.com/yuandian/tools) - 工具类库
