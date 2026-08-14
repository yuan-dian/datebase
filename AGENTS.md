# yuandian/database - Agent Guide

## What This Is

A lightweight PHP 8.1+ ORM library with annotation-driven models, multi-database support (MySQL, SQLite, MongoDB, Oracle), relations, soft deletes, and snowflake IDs.

**Package**: `yuandian/database` (Composer library)
**Namespace**: `yuandian\Database\` → `src/`

## Critical Facts

### Dependencies (Must Be Present)
- `yuandian/container` ^1.0 - Container/Facade system
- `yuandian/tools` dev-master - Utilities (SnowflakeUtil, UUIDUtil, StrUtil, BeanUtil, ClassReflector)
- `ext-pdo` required, `ext-mongodb` optional

### No Formal Test Framework
- No PHPUnit, no phpunit.xml, no test runner
- `tests/test.php` is a manual script requiring live MySQL at `127.0.0.1`
- Run with: `php tests/test.php`
- Test models in `tests/model/` (ShareBase, ShareFile, BaseModel)

### Database Config Contains Credentials
- `src/Config/database.php` has hardcoded production credentials
- Do NOT commit changes to this file with new credentials
- Tests override config via `Db::setConfig($config)` in test.php

### Code Style
- All files use `declare(strict_types=1)`
- Chinese comments throughout (author: 原点 <467490186@qq.com>)
- PSR-4 autoloading, no sub-namespace nesting beyond 2 levels
- Properties are public, typed, with default values

## Architecture

### Key Entry Points
- `Model\Model` - Base model class, all models extend this
- `DbManager` - Connection manager, accessed via `DB` facade
- `Facade\DB` - Static facade for `DbManager`
- `Db\BaseQuery` - Query builder (chainable)
- `Db\Connection` → `Db\PDOConnection` - Database connections

### Model Definition Pattern
```php
#[Table('table_name')]        // Optional: auto-derives from class name (camelCase → snake_case)
#[Connection('mysql')]        // Optional: uses default if omitted
#[SoftDelete]                 // Optional: soft delete support
#[AutoWriteTime]              // Optional: auto timestamps
class MyModel extends Model
{
    #[TableId(IdType::AUTO)]  // Required: AUTO, ASSIGN_ID (snowflake), or ASSIGN_UUID
    public int $id = 0;

    public string $name = ''; // camelCase property → snake_case column
}
```

### Relation Annotations
- `#[HasOne(Related::class, foreignKey, localKey)]`
- `#[HasMany(Related::class, foreignKey, localKey)]`
- `#[HasOneThrough(model, through, foreignKey, throughKey, localKey, throughPk)]`
- `#[HasManyThrough(model, through, foreignKey, throughKey, localKey, throughPk)]`

### Query Pattern
```php
// Static call → BaseQuery chain → terminal method
User::where('age', '>', 18)->select();  // Returns array
User::where('id', 1)->find();            // Returns model|null
User::with('relation')->select();        // Eager loading
```

## Directory Structure

```
src/
├── Attribute/          # PHP 8.1 attributes (Table, TableId, Connection, HasOne, etc.)
├── Config/
│   └── database.php    # Default config (hardcoded credentials - edit carefully)
├── Db/
│   ├── Builder/        # SQL builders per driver (Mysql, Oracle, Sqlite, Mongo)
│   ├── Connector/      # Driver connectors
│   ├── BaseQuery.php   # Query builder
│   ├── Connection.php  # Abstract connection
│   ├── PDOConnection.php
│   └── MongoQuery.php  # MongoDB-specific query
├── Enums/
│   └── IdType.php      # AUTO, ASSIGN_ID, ASSIGN_UUID
├── Exceptions/
├── Facade/
│   └── DB.php          # Static facade
├── Model/
│   ├── Model.php       # Base model (605 lines, core logic)
│   └── Relations/      # Relation implementations
└── DbManager.php       # Connection manager
tests/
├── test.php            # Manual test script
└── model/              # Test models
```

## Common Tasks

### Adding a New Model
1. Create class extending `yuandian\Database\Model\Model`
2. Add `#[Table]` annotation (or let it auto-derive)
3. Add `#[TableId]` to primary key property
4. Use camelCase for properties (auto-converts to snake_case columns)
5. Set typed defaults (0 for int, '' for string, null for nullable)

### Adding a New Relation
1. Add property with type hint
2. Add `#[HasOne]`/`#[HasMany]` annotation with foreign/local keys
3. Use `$model->load('relationName')` for lazy loading
4. Use `Model::with('relationName')` for eager loading

### Modifying Query Builder
- `src/Db/BaseQuery.php` - Main query builder
- `src/Db/Builder/Mysql.php` - MySQL-specific SQL generation
- Query methods return `$this` for chaining

## What NOT To Do

- Do NOT use PHPUnit (not installed)
- Do NOT run `composer update` without checking `yuandian/tools` dev-master compatibility
- Do NOT modify `src/Config/database.php` credentials
- Do NOT add new dependencies without checking yuandian/* packages first
- Do NOT use `as any` or suppress types (PHP doesn't have this, but avoid type juggling)
- Do NOT create Blade templates or views (this is a library, not an app)

## External References

- Dependencies: `vendor/yuandian/container/`, `vendor/yuandian/tools/`
- Related packages in same org: yuandian/container, yuandian/tools

## Quick Verification

```bash
# Check syntax
php -l src/Model/Model.php

# Run manual test (requires MySQL at 127.0.0.1)
php tests/test.php

# Check autoload
composer dump-autoload
```
