<?php

declare(strict_types=1);

namespace yuandian\Database\Scope;

use yuandian\Database\Db\BaseQuery;

/**
 * 全局作用域接口
 *
 * 实现类在 apply() 中为查询注入约束条件。
 * 通过 Model::addGlobalScope() 注册，查询时自动应用。
 */
interface Scope
{
    /**
     * 将作用域约束应用到查询器
     *
     * @param BaseQuery $query      查询器实例
     * @param string    $modelClass 模型类名（用于获取元数据）
     */
    public function apply(BaseQuery $query, string $modelClass): void;
}
