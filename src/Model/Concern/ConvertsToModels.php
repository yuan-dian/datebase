<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Model\Model;
use yuandian\Tools\utils\StrUtil;

/**
 * 模型转换能力：将数据库行数据转换为模型实例。
 *
 * 使用该 trait 的宿主类必须声明 protected string $modelClass 属性（模型类名）。
 *
 * @date 2026/5/7 上午11:07
 * @author 原点 467490186@qq.com
 */
trait ConvertsToModels
{
    /** @var array<string, array<string, string>> 列名→属性名反向映射缓存 */
    private static array $reverseMapCache = [];

    /**
     * 获取列名→属性名反向映射（静态缓存，含小写兜底键）
     *
     * @param class-string<Model> $modelClass
     * @return array<string, string>
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
     * 将数据库行数据转换为模型实例
     *
     * @param array<string, mixed> $row 数据库行数据（键为列名）
     */
    protected function toModel(array $row): Model
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
            $propName = $reverseMap[$column] ?? null;
            if ($propName === null) {
                $propName = StrUtil::camel($column);
                if (!property_exists($model, $propName)) {
                    continue;
                }
            }
            $isJson = array_key_exists($propName, $jsonColumns);
            if ($isJson) {
                $value = $model::castFromJson($value, $jsonColumns[$propName]);
            }

            if ($value !== null) {
                $model->$propName = $value;
            }

            if (isset($model->$propName)) {
                $original[$column] = $isJson ? $model::castToJson($model->$propName) : $model->$propName;
            }
        }
        $model->setOriginal($original);

        $softDelete = $meta->softDelete;
        if ($softDelete?->active() && !empty($row[$softDelete->column])) {
            $model->setSoftDeleted(true);
        }

        $model->fireAfterRead();

        return $model;
    }
}
