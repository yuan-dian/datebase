<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

/**
 * 模型水合（hydrate）能力：将数据库行数据转换为模型实例。
 *
 * 使用该 trait 的宿主类必须声明 protected string $modelClass 属性（模型类名）。
 *
 * @date 2026/5/7 上午11:07
 * @author 原点 467490186@qq.com
 */
trait HydratesModels
{
    /**
     * 列名→属性名反向映射缓存（按模型类），避免每行重复 array_flip
     *
     * @var array<string, array<string, string>>
     */
    private static array $reverseMapCache = [];

    /**
     * 获取列名→属性名反向映射（静态缓存，含小写兜底键）
     *
     * @param class-string<Model> $modelClass 模型类名
     * @return array<string, string> 列名→属性名（含小写键兜底）
     */
    protected function getReverseColumnMap(string $modelClass): array
    {
        if (!isset(self::$reverseMapCache[$modelClass])) {
            $map = [];
            foreach ($modelClass::getColumnMap() as $prop => $col) {
                $map[$col] = $prop;
                $map[strtolower($col)] = $prop;
            }
            self::$reverseMapCache[$modelClass] = $map;
        }

        return self::$reverseMapCache[$modelClass];
    }

    /**
     * 将数据库行数据水合为模型实例
     *
     * @param array<string, mixed> $row 数据库行数据（键为列名）
     * @return Model
     */
    protected function hydrate(array $row): Model
    {
        /** @var Model $model */
        $model = new $this->modelClass();
        $model->setExists(true);

        $meta = $model::getMeta();
        $columnMap = $meta->fields;
        $reverseMap = $this->getReverseColumnMap($this->modelClass);
        $jsonColumns = $meta->jsonColumns;

        $original = [];
        foreach ($row as $column => $value) {
            // 原样命中（Mysql/Sqlite 小写列）→ 小写兜底（Oracle wrap 强制大写列，如 CREATE_TIME → create_time）
            // 注意：camel 兜底保持原样输入，禁止 strtolower——否则 CamelCase 列（UserName → userName）会被破坏
            $propName = $reverseMap[$column] ?? null;
            if ($propName === null) {
                $propName = StrUtil::camel($column);
                if (!property_exists($model, $propName)) {
                    continue;
                }
            }
            // JSON 列：先反序列化再赋值
            $isJson = array_key_exists($propName, $jsonColumns);
            if ($isJson) {
                $value = $model::castFromJson($value, $jsonColumns[$propName]);
            }

            // NULL 跳过赋值：保留属性默认值，避免向非可空属性塞 null（TypePHP 类型不可变）
            if ($value !== null) {
                $model->$propName = $value;
            }

            // 快照：赋值后立即记录（复用已取 $value + $isJson 标记）
            if (isset($model->$propName)) {
                $original[$column] = $isJson ? $model::castToJson($model->$propName) : $model->$propName;
            }
        }
        $model->setOriginal($original);

        // 软删标记：软删列非空即已删（未声明该属性的模型也能感知状态）
        $softDelete = $meta->softDelete;
        if ($softDelete?->active() && !empty($row[$softDelete->column])) {
            $model->setSoftDeleted(true);
        }

        return $model;
    }
}
