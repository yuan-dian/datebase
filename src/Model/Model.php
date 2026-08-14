<?php

declare(strict_types=1);

namespace yuandian\Database\Model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\Connection;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Attribute\JsonColumn;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Db\BaseQuery;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Exceptions\DbException;
use yuandian\Database\Facade\DB;
use yuandian\Database\Model\Relations\HasManyRelation;
use yuandian\Database\Model\Relations\HasManyThroughRelation;
use yuandian\Database\Model\Relations\HasOneRelation;
use yuandian\Database\Model\Relations\HasOneThroughRelation;
use yuandian\Tools\bean\BeanUtil;
use yuandian\Tools\reflection\ClassReflector;
use yuandian\Tools\reflection\PropertyReflection;
use yuandian\Tools\utils\SnowflakeUtil;
use yuandian\Tools\utils\StrUtil;
use yuandian\Tools\utils\UUIDUtil;

/**
 * Class Model 模型基类
 * @mixin BaseQuery<static>
 * @method static static find()
 * @method static static[] select()
 */
abstract class Model
{
    // ===================== 静态缓存 =====================

    protected static array $_metaCache = [];

    // ===================== 实例状态 =====================

    /** 是否已存在于数据库 */
    protected bool $exists = false;

    /** 原始数据快照（用于 dirty 检测） */
    protected array $original = [];

    /** 已加载的关联 */
    protected array $loadedRelations = [];

    // ===================== 静态代理（链式入口）=====================

    /**
     * @return BaseQuery<static>
     */
    public static function __callStatic(string $method, array $args)
    {
        $model = new static();
        $db = $model->newQuery();
        if (!method_exists($db, $method)) {
            throw new DbException("Method '{$method}' does not exist on QueryBuilder");
        }
        return call_user_func_array([$db, $method], $args);
    }

    /**
     * @return BaseQuery<static>
     */
    public function newQuery(): BaseQuery
    {
        $connName = static::getConnectionName();

        $connection = Db::connect($connName);

        return $connection->newQuery(static::class);
    }

    // ===================== CRUD =====================

    /**
     * 保存模型（INSERT 或 UPDATE）
     */
    public function save(): bool
    {
        $data = $this->getDirtyData();

        if ($this->exists) {
            return $this->doUpdate($data);
        }

        return $this->doInsert($data);
    }

    /**
     * 删除当前模型
     */
    public function delete(): bool
    {
        $pkProp = static::getPkProperty();
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }

        $query = $this->newQuery();
        $query = $query->where(static::getPkColumn(), '=', $pkVal);

        $softDelete = static::getSoftDelete();
        if ($softDelete && $softDelete->enabled) {
            $query->update([$softDelete->column => date('Y-m-d H:i:s')]);
        } else {
            $query->delete();
        }

        return true;
    }

    /**
     * 强制删除（忽略软删除）
     */
    public function forceDelete(): bool
    {
        $pkProp = static::getPkProperty();
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }

        $query = $this->newQuery();
        $query->withoutGlobalScopes();
        $query->where(static::getPkColumn(), '=', $pkVal);
        $query->delete();

        return true;
    }

    // ===================== 属性访问 =====================

    /**
     * 获取所有属性值（用于序列化 / 调试）
     */
    public function toArray(): array
    {
        $data = [];
        $columnMap = static::getColumnMap();

        foreach ($columnMap as $prop => $column) {
            if (property_exists($this, $prop) && isset($this->$prop)) {
                $data[$column] = $this->$prop;
            }
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    // ===================== 关联 =====================

    /**
     * 预加载指定关联
     */
    public function load(string ...$relations): static
    {
        foreach ($relations as $relation) {
            if (!isset($this->loadedRelations[$relation])) {
                $this->loadRelation($relation);
            }
        }
        return $this;
    }

    /**
     * 批量预加载时写入关联结果（标记已加载并同步到属性）
     */
    public function setRelation(string $name, mixed $result): static
    {
        $this->loadedRelations[$name] = true;

        // 结果为 null 时跳过赋值：避免向非可空关联属性（如 Profile $profile）塞入 null（TypePHP 类型不可变）
        if ($result !== null && property_exists($this, $name)) {
            $this->$name = $result;
        }

        return $this;
    }

    protected function loadRelation(string $name): mixed
    {
        if (isset($this->loadedRelations[$name])) {
            return $this->loadedRelations[$name];
        }

        $info = static::getRelationInfo($name);
        if (!$info) {
            return null;
        }

        /**
         * @var HasOne|HasMany|HasOneThrough|HasManyThrough $attr
         */
        $attr = $info['attribute'];

        $result = match ($info['type']) {
            'HasOne' => (new HasOneRelation($this, $attr->model, $attr->foreignKey, $attr->localKey))->getResults(),
            'HasMany' => (new HasManyRelation($this, $attr->model, $attr->foreignKey, $attr->localKey))->getResults(),
            'HasOneThrough' => (new HasOneThroughRelation(
                $this, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ))->getResults(),
            'HasManyThrough' => (new HasManyThroughRelation(
                $this, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ))->getResults(),
            default => null,
        };

        $this->loadedRelations[$name] = true;

        // 同步到属性（null 时跳过赋值，兼容可空/非可空关联属性声明）
        if ($result !== null && property_exists($this, $name)) {
            $this->$name = $result;
        }

        return $result;
    }

    // ===================== 元数据 =====================


    public static function getMeta(): array
    {
        $class = static::class;
        if (!isset(self::$_metaCache[$class])) {
            self::$_metaCache[$class] = static::resolveMeta($class);
        }
        return self::$_metaCache[$class];
    }

    protected static function resolveMeta(string $class): array
    {
        $reflection = new ClassReflector($class);

        // Table name
        $tableAttr = $reflection->getAttribute(Table::class);
        $tableName = $tableAttr ? $tableAttr->name : StrUtil::snake($reflection->getShortName());

        // Connection name
        $connAttr = $reflection->getAttribute(Connection::class);
        $connectionName = $connAttr ? $connAttr->name : null;
        // SoftDelete
        $softDelete = $reflection->getAttributeFromHierarchy(SoftDelete::class);
        // AutoWriteTime
        $autoWriteTime = $reflection->getAttributeFromHierarchy(AutoWriteTime::class);

        $pkProperty = 'id';
        $pkColumn = 'id';
        $pkType = IdType::AUTO;


        // Fields, primary key, id type, relations
        $fields = [];       // [propertyName => columnName]
        $relations = [];    // [propertyName => relationDef]
        $jsonColumns = [];         // [propertyName => castTo|null]

        foreach ($reflection->getPublicProperties() as $prop) {
            $propName = $prop->getName();

            // Skip internal properties
            if (str_starts_with($propName, '_')) {
                continue;
            }
            $columnName = StrUtil::snake($propName);
            $fields[$propName] = $columnName;

            // Check for TableId attribute
            $tableIdAttr = $prop->getAttribute(TableId::class);
            if ($tableIdAttr) {
                $pkProperty = $propName;
                $pkColumn = $columnName;
                $pkType = $tableIdAttr->type;
            }
            // get the json column metadata
            $jsonColumnAttr = $prop->getAttribute(JsonColumn::class);
            if ($jsonColumnAttr) {
                $jsonColumns[$propName] = $jsonColumnAttr->castTo;
            }

            $relation = self::parseRelations($prop);
            if ($relation) {
                $relations[$propName] = $relation;
            }
        }

        return [
            'table'         => $tableName,
            'connection'    => $connectionName,
            'softDelete'    => $softDelete,
            'autoWriteTime' => $autoWriteTime,
            'fields'        => $fields,
            'pkProperty'    => $pkProperty,
            'pkColumn'      => $pkColumn,
            'pkType'        => $pkType,
            'relations'     => $relations,
            'jsonColumns'   => $jsonColumns,
        ];
    }

    /**
     * 获取表名
     */
    public static function getTableName(): string
    {
        return self::getMeta()['table'];
    }

    /**
     * 获取连接名
     */
    public static function getConnectionName(): ?string
    {
        return self::getMeta()['connection'] ?? null;
    }

    /**
     * 获取软删除标识
     */
    public static function getSoftDelete(): ?SoftDelete
    {
        return self::getMeta()['softDelete'] ?? null;
    }

    /**
     * 获取自动写入时间戳
     */
    public static function getAutoWriteTime(): ?AutoWriteTime
    {
        return self::getMeta()['autoWriteTime'] ?? null;
    }

    /**
     * 获取主键属性名
     */
    public static function getPkProperty(): string
    {
        return self::getMeta()['pkProperty'];
    }

    /**
     * 获取主键数据库列名
     */
    public static function getPkColumn(): string
    {
        return self::getMeta()['pkColumn'];
    }

    /**
     * 获取主键类型
     */
    public static function getPkType(): IdType
    {
        return self::getMeta()['pkType'];
    }

    /**
     * 获取属性→列名 映射表
     * @return array<string, string> [propertyName => columnName]
     */
    public static function getColumnMap(): array
    {
        return self::getMeta()['fields'];
    }

    /**
     * 获取关联元数据
     * @return array{type: string, attribute: object}|null
     */
    public static function getRelationInfo(string $name): ?array
    {
        return self::getMeta()['relations'][$name] ?? null;
    }

    /**
     * 获取属性→列名 映射表
     * @return array<string, string> [propertyName => columnName]
     */
    public static function getJsonColumns(): array
    {
        return self::getMeta()['jsonColumns'];
    }

    /**
     * 解析模型上关联注解
     */
    protected static function parseRelations(PropertyReflection $prop): array
    {
        $map = [
            HasOne::class         => 'HasOne',
            HasMany::class        => 'HasMany',
            HasOneThrough::class  => 'HasOneThrough',
            HasManyThrough::class => 'HasManyThrough',
        ];

        foreach ($map as $attrClass => $type) {
            if ($attrs = $prop->getAttribute($attrClass)) {
                return [
                    'type'      => $type,
                    'attribute' => $attrs,
                ];
            }
        }

        return [];
    }


    public function setExists(bool $exists): static
    {
        $this->exists = $exists;
        return $this;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    // ===================== 内部 CRUD =====================

    protected function doInsert(array $data): bool
    {
        $pkProp = static::getPkProperty();
        $pkColumn = static::getPkColumn();
        $pkType = static::getPkType();

        // 主键赋值策略
        if (empty($data[$pkColumn])) {
            $pkValue = match ($pkType) {
                IdType::AUTO => null,   // 由数据库自增
                IdType::ASSIGN_ID => SnowflakeUtil::nextId(),
                IdType::ASSIGN_UUID => UUIDUtil::fastUUID(),
            };

            if ($pkValue !== null) {
                $data[$pkColumn] = $pkValue;
                $this->$pkProp = $pkValue;
            }
        }
        $autoWriteTime = static::getAutoWriteTime();
        if ($autoWriteTime && $autoWriteTime->enabled) {
            $createTime = $autoWriteTime->createTime;
            if ($createTime !== false && !isset($data[$createTime])) {
                $data[$createTime] = date('Y-m-d H:i:s');
                $propertyCreateTime = StrUtil::camel($createTime);
                if (property_exists($this, $propertyCreateTime)) {
                    $this->$propertyCreateTime = $data[$createTime];
                }
            }
            $updateTime = $autoWriteTime->updateTime;
            if ($updateTime !== false && !isset($data[$updateTime])) {
                $data[$updateTime] = date('Y-m-d H:i:s');
                $propertyUpdateTime = StrUtil::camel($updateTime);
                if (property_exists($this, $propertyUpdateTime)) {
                    $this->$propertyUpdateTime = $data[$updateTime];
                }
            }
        }


        $query = $this->newQuery();;
        $id = $query->insert($data);

        // 自增主键回填（SQL 驱动返回 int；Mongo 驱动返回字符串 ID，需兼容）
        if ($pkType === IdType::AUTO && (int)$id > 0) {
            $this->$pkProp = $id;
        }

        $this->exists = true;
        $this->original = $data;

        return true;
    }

    protected function doUpdate(array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $pkProp = static::getPkProperty();
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }
        $autoWriteTime = static::getAutoWriteTime();
        if ($autoWriteTime && $autoWriteTime->enabled && $autoWriteTime->updateTime !== false && !isset($data[$autoWriteTime->updateTime])) {
            $updateTime = $autoWriteTime->updateTime;
            $data[$updateTime] = date('Y-m-d H:i:s');
            $propertyUpdateTime = StrUtil::camel($updateTime);
            if (property_exists($this, $propertyUpdateTime)) {
                $this->$propertyUpdateTime = $data[$updateTime];
            }
        }

        $query = $this->newQuery();
        $query->where(static::getPkColumn(), '=', $pkVal);
        $query->update($data);

        return true;
    }

    /**
     * 获取脏数据（仅返回已修改的字段）
     */
    protected function getDirtyData(): array
    {
        $data = [];
        $columnMap = static::getColumnMap();
        $jsonColumns = static::getJsonColumns();

        foreach ($columnMap as $prop => $column) {
            if (!property_exists($this, $prop) || !isset($this->$prop)) {
                continue;
            }

            $value = $this->$prop;

            // JSON 列：编码为 JSON 字符串
            if (array_key_exists($prop, $jsonColumns)) {
                $value = static::castToJson($value);
            }

            // 新记录：所有非 null 属性都写入
            // 已存在：仅写入变更的属性
            if (!$this->exists || ($this->original[$column] ?? null) !== $value) {
                $data[$column] = $value;
            }
        }
        return $data;
    }

    /**
     * 将 PHP 值序列化为 JSON 字符串（用于写入数据库）
     */
    public static function castToJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // 已经是 JSON 字符串
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }

        // JsonSerializable 接口
        if ($value instanceof \JsonSerializable) {
            return json_encode($value->jsonSerialize(), JSON_UNESCAPED_UNICODE);
        }

        // 普通对象 → 转数组后编码
        if (is_object($value)) {
            return json_encode(BeanUtil::objectToArray($value));
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 将数据库值反序列化为 PHP 值
     *
     * @param mixed $value 数据库原始值（string / array / null）
     * @param class-string<Object>|null $castTo 目标类名，null → 原生数组
     * @return array<Object>|Object|array
     */
    public static function castFromJson(mixed $value, ?string $castTo): mixed
    {
        if (empty($value)) {
            return $castTo !== null ? null : [];
        }
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (is_object($value)) {
            $value = $value instanceof \stdClass ? (array)$value : BeanUtil::objectToArray($value);
        }
        // 无目标类型 → 返回原生数组
        if ($castTo === null) {
            return $value;
        }

        // 空数组
        if (empty($value)) {
            return [];
        }

        // 判断是对象列表还是单个对象
        $isList = array_is_list($value) && is_array($value[0]);

        return $isList ? BeanUtil::arrayToObjectList($value, $castTo) : BeanUtil::arrayToObject($value, $castTo);
    }

    protected function toModel(array $row): Model
    {
        $model = new static();
        $model->setExists(true);
        $columnMap = static::getColumnMap();
        $reverseMap = array_flip($columnMap);
        $jsonColumns = static::getJsonColumns();

        foreach ($row as $column => $value) {
            $propName = $reverseMap[$column] ?? StrUtil::camel($column);
            if (property_exists($model, $propName)) {
                // JSON 列：先反序列化再赋值
                if (array_key_exists($propName, $jsonColumns)) {
                    $value = $model::castFromJson($value, $jsonColumns[$propName]);
                }

                // NULL 跳过赋值：保留属性默认值，避免向非可空属性塞 null（TypePHP 类型不可变）
                if ($value !== null) {
                    $model->$propName = $value;
                }
            }
        }

        return $model;
    }
}
