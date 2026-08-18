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

        $columnMap = $model::getColumnMap();
        $reverseMap = array_flip($columnMap);
        $jsonColumns = $model::getJsonColumns();

        foreach ($row as $column => $value) {
            $propName = $reverseMap[$column] ?? null;
            // 未命中映射：DB 新增列（不在模型 columnMap 中），camel 兜底并校验属性存在
            if ($propName === null) {
                $propName = StrUtil::camel($column);
                if (!property_exists($model, $propName)) {
                    continue;
                }
            }
            // JSON 列：先反序列化再赋值
            if (array_key_exists($propName, $jsonColumns)) {
                $value = $model::castFromJson($value, $jsonColumns[$propName]);
            }

            // NULL 跳过赋值：保留属性默认值，避免向非可空属性塞 null（TypePHP 类型不可变）
            if ($value !== null) {
                $model->$propName = $value;
            }
        }

        // 填充原始数据快照（dirty 检测基准）：存属性回读值（类型已由属性声明转换），
        // JSON 列编码为字符串，与 getDirtyData 的比较基准保持一致
        $original = [];
        foreach ($columnMap as $prop => $column) {
            if (!isset($model->$prop)) {
                continue;
            }
            $value = $model->$prop;
            if (array_key_exists($prop, $jsonColumns)) {
                $value = $model::castToJson($value);
            }
            $original[$column] = $value;
        }
        $model->setOriginal($original);

        return $model;
    }
}
