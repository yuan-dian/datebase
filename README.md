<p align="center">
    <strong>yuandian/database</strong>
</p>

<p align="center">
    一款基于 PHP 8.1+ 的轻量级 ORM 框架，支持多数据库驱动、注解驱动模型、关联映射、软删除、雪花算法主键等特性。
</p>

---

## 目录

- [环境要求](#环境要求)
- [安装](#安装)
- [数据库配置](#数据库配置)
- [快速开始](#快速开始)
- [模型定义](#模型定义)
    - [表名映射](#表名映射)
    - [主键策略](#主键策略)
    - [数据源指定](#数据源指定)
    - [字段映射规则](#字段映射规则)
- [CRUD 操作](#crud-操作)
    - [新增](#新增)
    - [查询](#查询)
    - [修改](#修改)
    - [删除](#删除)
- [Db 层独立使用](#db-层独立使用query-builder)
- [链式操作](#链式操作)
    - [WHERE 条件](#where-条件)
    - [字段选择](#字段选择)
    - [JOIN 查询](#join-查询)
    - [分组与聚合](#分组与聚合)
- [聚合查询](#聚合查询)
- [关联关系](#关联关系)
    - [一对一 HasOne](#一对一-hasone)
    - [一对多 HasMany](#一对多-hasmany)
    - [远程一对一 HasOneThrough](#远程一对一-hasonethrough)
    - [远程一对多 HasManyThrough](#远程一对多-hasmanythrough)
    - [懒加载](#懒加载)
    - [预加载](#预加载)
- [软删除](#软删除)
    - [基本用法](#基本用法)
    - [继承 BaseModel](#继承-basemodel)
    - [自定义列名](#自定义列名)
    - [禁用软删除](#禁用软删除)
- [自动时间戳](#自动时间戳)
    - [使用方法](#使用方法)
- [事务管理](#事务管理)
- [多数据源](#多数据源)
- [支持的数据库驱动](#支持的数据库驱动)
- [文件结构](#文件结构)
- [许可证](#许可证)

---

## 环境要求

| 依赖                 | 版本               |
|--------------------|------------------|
| PHP                | >= 8.1           |
| ext-pdo            | *                |
| ext-mongodb        | *（可选，MongoDB 支持） |
| yuandian/container | ^1.0             |
| yuandian/tools     | ^1.0             |

## 安装

```bash
composer require yuandian/database
```

## 快速开始

```php
<?php

use yuandian\Database\Attribute\Connection;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('user')]
#[Connection('mysql')]
class User extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $userName = '';

    public string $email = '';

    public int $age = 0;
}

// 新增
$user = new User();
$user->userName = '张三';
$user->email = 'zhangsan@example.com';
$user->age = 25;
$user->save();
echo "新增用户 ID: {$user->id}\n";

// 查询
$user = User::where('id', 1)->find();
echo $user->userName; // 张三

// 修改
$user->userName = '李四';
$user->save();

// 删除
$user->delete();

```

## 模型定义

### 表名映射

使用 `#[Table]` 注解指定表名。未使用注解时，自动将类名驼峰转下划线作为表名：

```php
#[Table('user_profile')]   // → 表名 user_profile
class UserProfile extends Model {}

class ArticleComment extends Model {}  // → 表名 article_comment
```

### 主键策略

使用 `#[TableId]` 注解声明主键属性及策略：

| 策略   | 枚举值                   | 说明               |
|------|-----------------------|------------------|
| 自增   | `IdType::AUTO`        | 数据库自增主键，插入后自动回填  |
| 雪花算法 | `IdType::ASSIGN_ID`   | 插入时自动生成数字型雪花 ID  |
| UUID | `IdType::ASSIGN_UUID` | 插入时自动生成字符串型 UUID |

```php
// 自增主键
class User extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
}

// 雪花算法主键
class Order extends Model
{
    #[TableId(IdType::ASSIGN_ID)]
    public int $orderId = 0;
}

// UUID 主键
class Event extends Model
{
    #[TableId(IdType::ASSIGN_UUID)]
    public string $eventId = '';
}
```

### 数据源指定

使用 `#[Connection]` 注解指定模型使用的数据源：

```php
#[Table('user')]
#[Connection('mysql')]    // → 使用 mysql 数据源
class User extends Model {}

#[Table('log')]
#[Connection('mongodb')]  // → 使用 mongodb 数据源
class Log extends Model {}
```

未使用 `#[Connection]` 注解时，使用配置中的默认数据源。

### 字段映射规则

模型属性使用小驼峰命名，数据库字段使用下划线命名，框架自动转换：

模型属性:`$userName` => 数据库字段:`user_name`

查询返回的数据会自动映射到模型属性，保存时自动转换为下划线字段名。

## CRUD 操作

### 新增

```php
$user = new User();
$user->userName = '张三';
$user->email = 'zhangsan@example.com';
$user->age = 25;
$user->save();
echo $user->id; // 主键自动回填
```

### 查询

```php
// 查询单条（返回模型实例或 null）
$user = User::where('id', 1)->find();

// 查询多条（返回模型实例数组）
$users = User::where('age', '>', 18)->select();

// 使用 with 预加载关联
$book = Book::with('chapters', 'isbn')->where('book_id', 1)->find();
```

### 修改

```php
$user = User::where('id', 1)->find();
$user->userName = '李四';
$user->save();
```

### 删除

```php
// 通过模型删除
$user = User::where('id', 1)->find();
$user->delete();

// 通过查询删除
User::where('id', 1)->delete();
```

## Db 层独立使用（Query Builder）

Db 层与模型层分层设计：**Db 层仅依赖数据表名，返回原生数组，不感知模型**；模型层是 Db 层的包装，负责水合（行转模型）、软删除、全局作用域与关联预加载。不使用模型时，可直接通过 `DB::table()` 操作数据表：

```php
use yuandian\Database\Facade\DB;

// 查询单条（返回原生数组或 null）
$row = DB::table('share_base')->where('share_id', '=', 2047600338951996096)->find();
echo $row['share_name'];

// 查询多条（返回数组列表）
$rows = DB::table('share_base')->where('user_id', '>', 0)->limit(10)->select();

// 聚合
$count = DB::table('share_base')->count();
$max   = DB::table('share_base')->max('share_id');

// 写入
DB::table('share_base')->insert(['share_name' => 'xxx']);
DB::table('share_base')->where('share_id', 1)->update(['share_name' => 'yyy']);
DB::table('share_base')->where('share_id', 1)->delete();
```

未指定表名时（`DB::table()` 不带参数返回空查询器），所有终端方法会抛出 `DbException`：

```php
DB::table()->where('id', 1)->find(); // 抛出 DbException：查询未指定数据表，请先调用 table()
```

**模型路径**（`User::where(...)`）内部走 `ModelQuery`，与 Db 层共享同一套查询构建、SQL 生成与执行链路，仅在结果返回时多一步水合。

## 链式操作

### WHERE 条件

```php
// 基本条件
User::where('age', '>', 18)->select();
User::where('userName', '张三')->select();         // 省略运算符，默认 =

// 批量条件
User::where(['userName' => '张三', 'age' => 25])->select();

// OR 条件
User::where('age', '>', 18)->orWhere('age', '<', 10)->select();

// IN / NOT IN
User::whereIn('id', [1, 2, 3])->select();
User::whereNotIn('id', [4, 5])->select();

// NULL / NOT NULL
User::whereNull('email')->select();
User::whereNotNull('email')->select();

// BETWEEN
User::whereBetween('age', 18, 30)->select();

// LIKE
User::whereLike('userName', '%张%')->select();

// 原始条件
User::whereRaw('age > ? AND id IN (?)', [18, [1, 2, 3]])->select();

// 嵌套条件组
User::where(function ($q) {
    $q->where('age', '>', 18)->orWhere('age', '<', 10);
})->select();
```

### 字段选择

```php
User::field('id,userName,email')->select();
User::field(['id', 'userName', 'email'])->select();
```

### JOIN 查询

```php
User::alias('u')
    ->leftJoin('profile AS p', 'u.id = p.user_id')
    ->field('u.userName, p.bio')
    ->select();
```

### 聚合查询

```php
$count = User::where('age', '>', 18)->count();
$sum   = User::sum('age');
$max   = User::max('age');
$min   = User::min('age');
$avg   = User::avg('age');
```

## 关联关系

关联通过属性注解定义，支持预加载与手动加载。

### 一对一 HasOne

```php
use yuandian\Database\Attribute\HasOne;

#[Table('user')]
class User extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public string $userName = '';
    #[HasOne(Profile::class, foreignKey: 'user_id', localKey: 'id')]
    public Profile $profile;
}

#[Table('profile')]
class Profile extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public int $userId = 0;
    public string $bio = '';
}

```

### 一对多 HasMany

```php
use yuandian\Database\Attribute\HasMany;

#[Table('book')]
class Book extends Model
{
    #[TableId(IdType::AUTO)]
    public int $bookId = 0;
    public string $bookName = '';

    #[HasMany(Chapter::class, foreignKey: 'book_id', localKey: 'book_id')]
    public array $chapters;
}

#[Table('chapter')]
class Chapter extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public int $bookId = 0;
    public string $name = '';
}
```

### 远程一对一 HasOneThrough

```php
use yuandian\Database\Attribute\HasOneThrough;

#[Table('book')]
class Book extends Model
{
    #[TableId(IdType::AUTO)]
    public int $bookId = 0;
    public int $authorId = 0;

    /**
     * Book → Profile（中间表）→ Address（目标表）
     */
    #[HasOneThrough(
        model: Address::class,
        through: Profile::class,
        foreignKey: 'author_id',
        throughKey: 'profile_id',
        localKey: 'book_id',
        throughPk: 'id'
    )]
    public Address $address;
}

```

### 远程一对多 HasManyThrough

```php
use yuandian\Database\Attribute\HasOneThrough;

#[Table('book')]
class Book extends Model
{
    #[TableId(IdType::AUTO)]
    public int $bookId = 0;

    /**
     * Book → BookTag（中间表）→ Tag（目标表）
     */
    #[HasManyThrough(
        model: Tag::class,
        through: BookTag::class,
        foreignKey: 'book_id',
        throughKey: 'tag_id',
        localKey: 'book_id',
        throughPk: 'id'
    )]
    /*** @var array<Tag>*/
    public array $tags;
}
```

参数说明：

| 参数           | 说明            |
|--------------|---------------|
| `model`      | 目标关联模型        |
| `through`    | 中间模型          |
| `foreignKey` | 当前模型 → 中间表的外键 |
| `throughKey` | 中间表 → 目标表的外键  |
| `localKey`   | 当前模型的本地键      |
| `throughPk`  | 中间表的主键        |

### 预加载

使用 `with()` 或 `load()` 预加载关联，避免 N+1 查询问题：

```php
// 预加载（在查询时一次性加载关联数据）
$books = Book::with('chapters', 'isbn')
    ->where('author_id', 1)
    ->select();

foreach ($books as $book) {
    echo $book->bookName;
    // 以下访问均命中缓存，无额外查询
    foreach ($book->chapters as $ch) {
        echo "  - {$ch->name}";
    }
    echo $book->isbn->isbn;
}

// 查询后按需加载
$book = Book::where('book_id', 1)->find();
$book->load('chapters', 'isbn');
```

## 软删除

### 基本用法

在模型类上使用 #[SoftDelete] 注解启用软删除：

```php
use yuandian\Database\Attribute\SoftDelete;

#[Table('article')]
#[SoftDelete] // 默认列名: deleted_time
class Article extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public string $title = '';
    public string $content = '';
}

```

### 自定义列名

```php
#[SoftDelete('deleted_at')]
class Article extends Model
{
}
```

### 继承 BaseModel

在 BaseModel 上统一声明软删除，所有子模型自动继承：

```php
#[Connection('mysql')]
#[SoftDelete]
class BaseModel extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
}

#[Table('article')]
class Article extends BaseModel   // 自动继承 #[SoftDelete]
{
    public string $title = '';
    public string $content = '';
}
```

### 禁用软删除

子类可通过 enabled: false 显式禁用父类的软删除：

```php
#[SoftDelete(enabled: false)]
class Article extends Model
{
}
```

## 自动时间戳

### 使用方法

在 BaseModel 上统一声明自动时间戳，所有子模型自动继承：

```php
// 使用默认列名 create_time，update_time
#[AutoWriteTime]
class Article extends Model
{
}

// 禁用
#[AutoWriteTime(enabled: false)]
class Article extends Model
{
}

// 指定createTime列名：create_at
#[AutoWriteTime(createTime: 'create_at')]
class Article extends Model
{
}

// 禁用 createTime 写入
#[AutoWriteTime(createTime: false)]
class Article extends Model
{
}

// 指定 updateTime 列名：update_at
#[AutoWriteTime(updateTime: 'update_at')] → 列名 update_at
class Article extends Model
{
}
// 禁用 updateTime 写入
#[AutoWriteTime(updateTime: false)]
class Article extends Model
{
}
```

## 事务管理

### 闭包事务（推荐）

```php
use yuandian\Database\Facade\DB;

DB::transaction(function () {
    $book = new Book();
    $book->bookName = '事务测试';
    $book->authorId = 1;
    $book->save();

    $chapter = new Chapter();
    $chapter->bookId = $book->bookId;
    $chapter->name = '第一章';
    $chapter->save();
});
// 自动 commit，异常时自动 rollback
```

### 手动事务

```php
use yuandian\Database\Facade\DB;

DB::beginTransaction();
try {
    $book = new Book();
    $book->bookName = '手动事务';
    $book->authorId = 1;
    $book->save();

    DB::commit();
} catch (\Throwable $e) {
    DB::rollback();
    throw $e;
}
```