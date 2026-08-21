<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Db\BaseQuery;

/**
 * ModelQuery 共享方法：PDO 侧 ModelQuery 与 Mongo 侧 MongoModelQuery 共用。
 *
 * @date 2026/8/21
 * @author 原点 <467490186@qq.com>
 */
trait ModelQueryShared
{
    /** @var class-string */
    protected string $modelClass;

    /**
     * 获取模型类名
     * @return class-string
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * 字段名转换：属性名（camelCase）→ 列名（snake_case），基于元数据反查。
     *
     * 仅转换已声明的属性名；非属性名（如真实列名、SQL 片段）原样返回。
     * 限定名 table.column 只转换列段。
     */
    protected function convertFieldName(string $field): string
    {
        if ($field === '') {
            return $field;
        }

        $dot = strrpos($field, '.');
        if ($dot !== false) {
            $table = substr($field, 0, $dot);
            $column = substr($field, $dot + 1);
            $columnMap = $this->modelClass::getColumnMap();

            return $table . '.' . ($columnMap[$column] ?? $column);
        }

        $columnMap = $this->modelClass::getColumnMap();

        return $columnMap[$field] ?? $field;
    }

    /**
     * 创建同连接的子查询实例
     */
    protected function newSubQuery(): static
    {
        return new static($this->connection, $this->modelClass);
    }

    /**
     * chunk 分块时同步查询状态与预加载名
     */
    protected function copyExtraState(BaseQuery $query): void
    {
        /** @var static $query */
        $query->state = $this->state->copy();
        if (!empty($this->withRelations)) {
            $query->withRelations = $this->withRelations;
        }
    }
}
