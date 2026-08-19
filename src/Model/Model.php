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
use yuandian\Database\Db\Connector\Mongo;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Enums\RelationType;
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
     * 显式查询入口：真实静态方法，IDE 友好（返回类型由 PHPDoc 精化）
     *
     * 新代码推荐 `Model::query()->where(...)->find()`；存量 `Model::where(...)` 由
     * __callStatic 代理保持兼容。
     *
     * @return ModelQuery<static>|MongoModelQuery<static>
     */
    public static function query(): BaseQuery
    {
        return static::newQueryForClass(static::class);
    }

    /**
     * 静态代理（向后兼容保留）：推荐改用 query() 入口
     *
     * @param string $method
     * @param array<int, mixed> $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        $query = static::query();
        if (!method_exists($query, $method)) {
            throw new DbException("Method '{$method}' does not exist on QueryBuilder");
        }
        return $query->{$method}(...$args);
    }

    /**
     * 为指定模型类创建模型查询实例
     *
     * @param class-string<Model> $class
     * @return BaseQuery
     */
    public static function newQueryForClass(string $class): BaseQuery
    {
        $connection = Db::connect($class::getConnectionName());

        if ($connection instanceof Mongo) {
            return new MongoModelQuery($connection, $class);
        }

        return new ModelQuery($connection, $class);
    }

    // ===================== CRUD =====================

    /**
     * 保存模型（INSERT 或 UPDATE 便捷入口）
     *
     * 按当前状态自动路由：已存在（hydrate/insert 成功过）走 update()，否则走 insert()。
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->update();
        }

        return $this->insert();
    }

    /**
     * 强制新增语义：所有非 null 属性写入，忽略当前 exists 状态。
     *
     * 显式赋 null 的属性不写入（沿用数据库默认值）；成功后 exists 置 true 并同步快照。
     */
    public function insert(): bool
    {
        return $this->doInsert($this->getInsertData());
    }

    /**
     * 强制更新语义：仅写入与快照不同的字段（dirty 检测），忽略当前 exists 状态。
     *
     * 主键为 null 时返回 false；成功后同步快照，避免连续 save() 重复 UPDATE。
     */
    public function update(): bool
    {
        return $this->doUpdate($this->getUpdateData());
    }

    /**
     * 删除当前模型
     *
     * 按实际影响行数返回成功与否；删除成功后 exists 置 false。
     */
    public function delete(): bool
    {
        $pkProp = static::getPkProperty();
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }

        $query = static::query();
        $query = $query->where(static::getPkColumn(), '=', $pkVal);

        $softDelete = static::getSoftDelete();
        $affected = 0;
        if ($softDelete && $softDelete->enabled) {
            $affected = $query->update([$softDelete->column => date('Y-m-d H:i:s')]);
        } else {
            $affected = $query->delete();
        }

        if ($affected > 0) {
            $this->exists = false;
        }

        return $affected > 0;
    }

    /**
     * 强制删除（忽略软删除）
     *
     * 按实际影响行数返回成功与否；删除成功后 exists 置 false。
     */
    public function forceDelete(): bool
    {
        $pkProp = static::getPkProperty();
        $pkVal = $this->$pkProp ?? null;

        if ($pkVal === null) {
            return false;
        }

        $query = static::query();
        $query->withoutGlobalScopes();
        $query->where(static::getPkColumn(), '=', $pkVal);

        $affected = $query->delete();

        if ($affected > 0) {
            $this->exists = false;
        }

        return $affected > 0;
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
            // 仅丢弃 null 与未初始化属性；空数组 []、0、'' 均保留（?? 对 uninitialized typed property 不抛错）
            if (($this->$prop ?? null) !== null) {
                $data[$prop] = $this->$prop;
            }
        }

        // 已加载的关联属性（不在 columnMap 中）同样参与序列化
        foreach (static::getMeta()['relations'] as $prop => $relation) {
            if (($this->$prop ?? null) !== null) {
                $data[$prop] = $this->$prop;
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
    public function setRelation(string $name, Model|array|null $result): static
    {
        $this->loadedRelations[$name] = true;

        if (property_exists($this, $name)) {
            if ($result !== null) {
                $this->$name = $result;
            } elseif ($this->isNullableProperty($name)) {
                // 可空属性：赋 null 默认值，避免无默认值属性访问时抛 "must not be accessed before initialization"
                $this->$name = null;
            }
        }

        return $this;
    }

    /**
     * 属性是否允许 null（可空类型或无类型声明）
     */
    protected function isNullableProperty(string $name): bool
    {
        return self::getMeta()['nullable'][$name] ?? false;
    }

    /**
     * 加载关联结果
     *
     * @return Model|array|null 关联结果（Model[] 为 HasMany 系列；Model 为 HasOne 系列）
     */
    protected function loadRelation(string $name): Model|array|null
    {
        if (isset($this->loadedRelations[$name])) {
            // 已加载标记仅用于短路；load() 入口已做 isset 守卫，此处直接返回 null
            return null;
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
            RelationType::HasOne => (new HasOneRelation($this, $attr->model, $attr->foreignKey, $attr->localKey))->getResults(),
            RelationType::HasMany => (new HasManyRelation($this, $attr->model, $attr->foreignKey, $attr->localKey))->getResults(),
            RelationType::HasOneThrough => (new HasOneThroughRelation(
                $this, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ))->getResults(),
            RelationType::HasManyThrough => (new HasManyThroughRelation(
                $this, $attr->model, $attr->through,
                $attr->foreignKey, $attr->throughKey, $attr->localKey, $attr->throughPk
            ))->getResults(),
            default => null,
        };

        $this->loadedRelations[$name] = true;

        // 同步到属性（null 时仅对可空属性赋默认值，避免未初始化访问崩溃；非可空属性如 Profile 保持跳过）
        if (property_exists($this, $name)) {
            if ($result !== null) {
                $this->$name = $result;
            } elseif ($this->isNullableProperty($name)) {
                $this->$name = null;
            }
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
        $jsonColumns = [];  // [propertyName => castTo|null]
        $nullable = [];     // ['propertyName' => bool]

        foreach ($reflection->getPublicProperties() as $prop) {
            $propName = $prop->getName();

            // Skip internal properties
            if (str_starts_with($propName, '_')) {
                continue;
            }
            // 判断属性是否可以为null
            $type = $prop->getType();
            if ($type === null || $type->allowsNull()) {
                $nullable[$propName] = true;
            }


            // 关联属性不是数据库列：不进入 fields（columnMap），
            // 避免 getUpdateData 把已加载的关联对象当列值写入数据库
            $relation = self::parseRelations($prop);
            if ($relation) {
                $relations[$propName] = $relation;
            } else {
                $columnName = StrUtil::snake($propName);
                $fields[$propName] = $columnName;
            }

            // Check for TableId attribute
            $tableIdAttr = $prop->getAttribute(TableId::class);
            if ($tableIdAttr) {
                $pkProperty = $propName;
                $pkColumn = StrUtil::snake($propName);
                $pkType = $tableIdAttr->type;
            }
            // get the json column metadata
            $jsonColumnAttr = $prop->getAttribute(JsonColumn::class);
            if ($jsonColumnAttr) {
                $jsonColumns[$propName] = $jsonColumnAttr->castTo;
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
            'nullable'      => $nullable,
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
            HasOne::class         => RelationType::HasOne,
            HasMany::class        => RelationType::HasMany,
            HasOneThrough::class  => RelationType::HasOneThrough,
            HasManyThrough::class => RelationType::HasManyThrough,
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

    /**
     * 写入原始数据快照（dirty 检测基准，由查询层填充）
     */
    public function setOriginal(array $original): static
    {
        $this->original = $original;
        return $this;
    }

    public function getOriginal(): array
    {
        return $this->original;
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
            } elseif ($pkType === IdType::AUTO) {
                // AUTO：移除残留的主键 0 值，交由数据库自增（避免显式 id=0 导致二次插入 UNIQUE 冲突）
                unset($data[$pkColumn]);
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


        $query = static::query();
        $id = $query->insert($data);

        // 自增主键回填（SQL 驱动返回 int；Mongo 驱动返回字符串 ID，需兼容）
        if ($pkType === IdType::AUTO && (int)$id > 0) {
            $this->$pkProp = $id;
            $data[$pkColumn] = $id; // 回填快照，保持 original 含主键
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

        // 主键无效（null/0/''）视为未持久化的模型，拒绝 UPDATE
        if (empty($pkVal)) {
            return false;
        }

        // 主键永不作为 SET 字段写入（防止 UPDATE 误改主键列）
        unset($data[static::getPkColumn()]);
        if (empty($data)) {
            return true;
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

        $query = static::query();
        $query->where(static::getPkColumn(), '=', $pkVal);
        $query->update($data);

        // 同步快照：避免连续 save() 将已写字段再次判定为 dirty，重复执行 UPDATE
        $this->original = array_merge($this->original, $data);

        return true;
    }

    /**
     * 获取新增数据：所有非 null 属性（null 表示沿用数据库默认值）。
     *
     * 属性未赋值（保持默认值）不参与——用 property_exists 而非 isset，避免 null 被误判为未赋值。
     */
    protected function getInsertData(): array
    {
        $data = [];
        $columnMap = static::getColumnMap();
        $jsonColumns = static::getJsonColumns();

        foreach ($columnMap as $prop => $column) {
            if (!property_exists($this, $prop)) {
                continue;
            }

            $value = $this->$prop;

            // JSON 列：编码为 JSON 字符串
            if (array_key_exists($prop, $jsonColumns)) {
                $value = static::castToJson($value);
            }

            // 新增语义：所有非 null 属性都写入（null 表示沿用数据库默认值）
            if ($value !== null) {
                $data[$column] = $value;
            }
        }
        return $data;
    }

    /**
     * 获取更新数据：仅返回与快照不同的字段（dirty 检测）。
     *
     * null 语义：显式赋 null 的属性参与 dirty 比较（快照非 null → 写入 NULL 清空字段）；
     * 属性未赋值（保持默认值）不参与——用 property_exists 而非 isset，避免 null 被误判为未赋值。
     */
    protected function getUpdateData(): array
    {
        $data = [];
        $columnMap = static::getColumnMap();
        $jsonColumns = static::getJsonColumns();

        foreach ($columnMap as $prop => $column) {
            if (!property_exists($this, $prop)) {
                continue;
            }

            $value = $this->$prop;

            // JSON 列：编码为 JSON 字符串
            if (array_key_exists($prop, $jsonColumns)) {
                $value = static::castToJson($value);
            }

            // 更新语义：仅写入变更的属性（null 参与比较，显式赋 null 会写入）
            if (($this->original[$column] ?? null) !== $value) {
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
     * @return array|object|null 反序列化结果
     */
    public static function castFromJson(mixed $value, ?string $castTo): array|object|null
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

    public function __debugInfo(): array
    {
        return $this->toArray();
    }
}
