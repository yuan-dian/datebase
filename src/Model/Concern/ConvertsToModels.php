<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Model\Model;
use yuandian\Database\Model\ModelMeta;
use yuandian\Tools\utils\StrUtil;

trait ConvertsToModels
{
    /** @var array<string, array<string, string>> */
    private static array $reverseMapCache = [];

    /** @var class-string<Model> $modelClass */
    protected function getReverseColumnMap(string $modelClass): array
    {
        if (!isset(self::$reverseMapCache[$modelClass])) {
            $map = [];
            foreach ($modelClass::getColumnMap() as $prop => $col) {
                $map[$col] = $prop;
                $map[strtolower($col)] = $prop;
                $camel = StrUtil::camel($col);
                if (!isset($map[$camel])) {
                    $map[$camel] = $prop;
                }
            }
            self::$reverseMapCache[$modelClass] = $map;
        }

        return self::$reverseMapCache[$modelClass];
    }

    protected function toModel(array $row): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;
        $meta = $modelClass::getMeta();

        return $this->hydrateRow(
            $row,
            $modelClass,
            $meta,
            $this->getReverseColumnMap($modelClass)
        );
    }

    /**
     * 批量水合：元数据只获取一次，避免 O(n) 次重复调用。
     *
     * @param list<array> $rows
     * @return list<Model>
     */
    protected function toModels(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $modelClass = $this->modelClass;
        $meta = $modelClass::getMeta();
        $reverseMap = $this->getReverseColumnMap($modelClass);

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrateRow($row, $modelClass, $meta, $reverseMap);
        }

        return $models;
    }

    /**
     * 单行水合：列映射 → 类型转换 → 软删除标记 → afterRead 事件。
     */
    private function hydrateRow(
        array $row,
        string $modelClass,
        ModelMeta $meta,
        array $reverseMap
    ): Model {
        /** @var Model $model */
        $model = new $modelClass();
        $model->setExists(true);

        $original = [];
        $casters = $meta->casters;
        foreach ($row as $column => $value) {
            $propName = $reverseMap[$column] ?? null;
            if ($propName === null) {
                continue;
            }

            $original[$column] = $value;

            $caster = $casters[$propName] ?? null;
            $resolved = $caster !== null ? $caster->fromDb($value) : $value;

            if ($resolved !== null) {
                $model->$propName = $resolved;
            }
        }
        $model->setOriginal($original);

        $softDelete = $meta->softDelete;
        if ($softDelete?->active() && !empty($row[$softDelete->column])) {
            $model->setSoftDeleted(true);
        }

        $model->triggerAfterRead();

        return $model;
    }
}
