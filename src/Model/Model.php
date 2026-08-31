<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Exceptions\DbException;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Relations\Relation;
use yuandian\Database\Model\Relations\RelationFactory;
use yuandian\Database\Scope\Scope;
use yuandian\Tools\utils\SnowflakeUtil;
use yuandian\Tools\utils\StrUtil;
use yuandian\Tools\utils\UUIDUtil;
use yuandian\Database\Model\Concern\HasEvents;
use yuandian\Database\Model\Concern\HasMetadata;
use yuandian\Database\Model\Concern\HasAttributes;

/**
 * Class Model 模型基类
 *
 * @template TModel of static
 * @mixin ModelQuery<TModel>
 * @method static TModel|null find()
 * @method static TModel[] select()
 * @method static int count(string $field = '*')
 * @method static float sum(string $field)
 * @method static float avg(string $field)
 * @method static string|int|float|null min(string $field)
 * @method static string|int|float|null max(string $field)
 * @method static mixed value(string $field, string $key = '')
 * @method static array column(string $field, string $key = '')
 * @method static int|string insert(array $data)
 * @method static int insertAll(array $dataList)
 * @method static int update(array $data)
 * @method static int delete()
 */
abstract class Model
{
    use HasEvents;
    use HasMetadata;
    use HasAttributes;

    // ===================== 实例状态 =====================

    protected bool $exists = false;
    protected array $original = [];
    protected array $loadedRelations = [];
    protected bool $softDeleted = false;
    /** @var array<string, Relation> */
    protected array $relationCache = [];

    // ===================== 实例状态访问器 =====================

    public function exists(): bool
    {
        return $this->exists;
    }

    public function setExists(bool $exists): static
    {
        $this->exists = $exists;
        return $this;
    }

    public function isTrashed(): bool
    {
        return $this->softDeleted;
    }

    public function setSoftDeleted(bool $softDeleted): static
    {
        $this->softDeleted = $softDeleted;
        return $this;
    }

    public function getOriginal(): array
    {
        return $this->original;
    }

    public function setOriginal(array $original): static
    {
        $this->original = $original;
        return $this;
    }

    // ===================== 全局作用域 =====================

    /** @var array<class-string, array<class-string, Scope>> */
    protected static array $globalScopes = [];

    /**
     * @param class-string<Scope> $scopeClass
     */
    public static function addGlobalScope(string $scopeClass): void
    {
        static::$globalScopes[static::class][$scopeClass] = new $scopeClass();
    }

    /**
     * @param class-string $scopeClass
     */
    public static function removeGlobalScope(string $scopeClass): void
    {
        unset(static::$globalScopes[static::class][$scopeClass]);
    }

    /** @return array<class-string, Scope> */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    protected static function registerScopes(): void
    {
    }

    // ===================== 查询入口 =====================

    /**
     * @return ModelQuery<static>|MongoModelQuery<static>
     */
    public static function query(): BaseQuery
    {
        return static::newQueryForClass(static::class);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        $query = static::query();
        if (!method_exists($query, $method)) {
            throw new DbException("Method '{$method}' does not exist on QueryBuilder");
        }
        return $query->{$method}(...$args);
    }

    public static function newQueryForClass(string $class): BaseQuery
    {
        $connection = Db::connect($class::getConnectionName());
        return $connection->createModelQuery($class);
    }

    // ===================== CRUD =====================

    public function save(): bool
    {
        if ($this->exists) {
            return $this->update();
        }
        return $this->insert();
    }

    public function insert(): bool
    {
        $meta = static::getMeta();
        if ($this->triggerEvent('beforeInsert', $meta)) {
            return false;
        }
        return $this->doInsert($this->getInsertData(), $meta);
    }

    public function update(): bool
    {
        $meta = static::getMeta();
        if ($this->triggerEvent('beforeUpdate', $meta)) {
            return false;
        }
        return $this->doUpdate($this->getUpdateData(), $meta);
    }

    public function delete(): bool
    {
        $meta = static::getMeta();
        $pkProp = $meta->pkProperty;
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }

        if ($this->triggerEvent('beforeDelete', $meta)) {
            return false;
        }

        $affected = static::query()->where($meta->pkColumn, '=', $pkVal)->delete();

        if ($affected > 0) {
            $this->exists = false;
            $this->softDeleted = true;
            $this->triggerEvent('afterDelete', $meta);
        }

        return $affected > 0;
    }

    public function forceDelete(): bool
    {
        $meta = static::getMeta();
        $pkProp = $meta->pkProperty;
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }

        if ($this->triggerEvent('beforeForceDelete', $meta)) {
            return false;
        }

        $affected = static::query()->force()->where($meta->pkColumn, '=', $pkVal)->delete();

        if ($affected > 0) {
            $this->exists = false;
            $this->triggerEvent('afterForceDelete', $meta);
        }

        return $affected > 0;
    }

    public function restore(): bool
    {
        $meta = static::getMeta();
        $pkProp = $meta->pkProperty;
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }

        if ($this->triggerEvent('beforeRestore', $meta)) {
            return false;
        }

        $affected = static::query()->where($meta->pkColumn, '=', $pkVal)->restore();

        if ($affected > 0) {
            $this->softDeleted = false;
            $this->exists = true;
            $this->triggerEvent('afterRestore', $meta);
        }

        return $affected > 0;
    }

    // ===================== 关联 =====================

    public function load(string ...$relations): static
    {
        foreach ($relations as $relation) {
            if (!isset($this->loadedRelations[$relation])) {
                $this->loadRelation($relation);
            }
        }
        return $this;
    }

    public function setRelation(string $name, Model|array|null $result): static
    {
        $this->loadedRelations[$name] = true;

        if (property_exists($this, $name)) {
            if ($result !== null) {
                $this->$name = $result;
            } elseif ($this->isNullableProperty($name)) {
                $this->$name = null;
            }
        }

        return $this;
    }

    protected function loadRelation(string $name): Model|array|null
    {
        if (isset($this->loadedRelations[$name])) {
            return null;
        }

        $info = static::getRelationInfo($name);
        if (!$info) {
            return null;
        }

        /** @var HasOne|HasMany|HasOneThrough|HasManyThrough $attr */
        $attr = $info['attribute'];

        if (!isset($this->relationCache[$name])) {
            $this->relationCache[$name] = RelationFactory::create($this, $info['type'], $attr);
            if ($this->relationCache[$name] === null) {
                unset($this->relationCache[$name]);
                return null;
            }
        }

        $result = $this->relationCache[$name]->getResults();

        $this->loadedRelations[$name] = true;

        if (property_exists($this, $name)) {
            if ($result !== null) {
                $this->$name = $result;
            } elseif ($this->isNullableProperty($name)) {
                $this->$name = null;
            }
        }

        return $result;
    }

    protected function isNullableProperty(string $name): bool
    {
        return self::getMeta()->nullable[$name] ?? false;
    }

    // ===================== 内部 CRUD =====================

    protected function doInsert(array $data, ModelMeta $meta): bool
    {
        $data = $this->resolvePkForInsert($data, $meta);
        $this->applyAutoWriteTime($data, true, $meta);

        $id = static::query()->insert($data);

        $this->backfillAutoIncrement($id, $meta);
        $this->exists = true;
        $this->original = $data;
        $this->triggerEvent('afterInsert', $meta);

        return true;
    }

    protected function doUpdate(array $data, ModelMeta $meta): bool
    {
        if (empty($data)) {
            return true;
        }

        $pkVal = $this->{$meta->pkProperty} ?? null;
        if (empty($pkVal)) {
            return false;
        }

        unset($data[$meta->pkColumn]);
        if (empty($data)) {
            return true;
        }

        $this->applyAutoWriteTime($data, false, $meta);

        $affected = static::query()
            ->where($meta->pkColumn, '=', $pkVal)
            ->update($data);

        if ($affected === 0) {
            return false;
        }

        $this->original = array_merge($this->original, $data);
        $this->triggerEvent('afterUpdate', $meta);

        return true;
    }

    protected function resolvePkForInsert(array $data, ModelMeta $meta): array
    {
        $pkColumn = $meta->pkColumn;
        $pkType = $meta->pkType;

        if (!empty($data[$pkColumn])) {
            return $data;
        }

        $pkValue = match ($pkType) {
            IdType::AUTO => null,
            IdType::ASSIGN_ID => SnowflakeUtil::nextId(),
            IdType::ASSIGN_UUID => UUIDUtil::fastUUID(),
        };

        if ($pkValue !== null) {
            $data[$pkColumn] = $pkValue;
            $this->{$meta->pkProperty} = $pkValue;
        } elseif ($pkType === IdType::AUTO) {
            unset($data[$pkColumn]);
        }

        return $data;
    }

    protected function applyAutoWriteTime(array &$data, bool $isInsert, ModelMeta $meta): void
    {
        $autoWriteTime = $meta->autoWriteTime;
        if (!$autoWriteTime || !$autoWriteTime->enabled) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        if ($isInsert && $autoWriteTime->createTime !== false && !isset($data[$autoWriteTime->createTime])) {
            $data[$autoWriteTime->createTime] = $now;
            $this->syncPropertyFromColumn($autoWriteTime->createTime, $now);
        }

        if ($autoWriteTime->updateTime !== false && !isset($data[$autoWriteTime->updateTime])) {
            $data[$autoWriteTime->updateTime] = $now;
            $this->syncPropertyFromColumn($autoWriteTime->updateTime, $now);
        }
    }

    protected function syncPropertyFromColumn(string $column, mixed $value): void
    {
        $property = StrUtil::camel($column);
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }

    protected function backfillAutoIncrement(int|string $id, ModelMeta $meta): void
    {
        if ($meta->pkType === IdType::AUTO && (int)$id > 0) {
            $this->{$meta->pkProperty} = $id;
        }
    }

    // ===================== 调试 =====================

    public function __debugInfo(): array
    {
        return $this->toArray();
    }
}
