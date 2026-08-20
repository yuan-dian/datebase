<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Db\State\WhereCondition;

/**
 * 模型层软删除查询能力：全局作用域过滤、软删/物理删、强制删除。
 *
 * 软删除是模型层语义（Db 层无此概念）：本 trait 是软删逻辑的唯一归属。
 * 使用该 trait 的宿主类必须声明 protected string $modelClass 属性（模型类名）。
 *
 * 职责：
 *  - applyGlobalScopes()：查询时统一注入软删过滤（whereNull，PDO/Mongo 同构）
 *  - delete()：软删（幂等更新时间戳列）/ 物理删（force() 或模型未启用软删）
 *  - restore()：批量恢复已删行
 *  - withTrashed()/onlyTrashed()：含已删/仅已删查询
 *  - force()：标记物理删除
 *
 * @date 2026/8/20
 * @author 原点 467490186@qq.com
 */
trait HasSoftDeleteQuery
{
    /** @var bool 是否强制物理删除（force() 设置） */
    protected bool $forceDelete = false;

    /**
     * 标记物理删除：delete() 时跳过软删语义，直接删除物理行。
     */
    public function force(): static
    {
        $this->forceDelete = true;

        return $this;
    }

    /**
     * 删除：软删（默认）或物理删（force() 或模型未启用软删）。
     *
     * 删除动作一律临时移除软删作用域（自身过滤不参与删除匹配）：
     *  - 软删：更新时间戳列——幂等，已删行重复删除仍返回 1
     *  - 物理删：parent::delete() 命中全部匹配行（含已软删行）
     */
    public function delete(): int
    {
        $softDelete = $this->modelClass::getSoftDelete();
        $hasSoftDelete = $softDelete !== null && $softDelete->active();

        $savedRemoved = $this->removedScopes;
        $this->removedScopes[] = 'softDelete';
        try {
            if ($this->forceDelete || !$hasSoftDelete) {
                return parent::delete();
            }

            return $this->update([$softDelete->column => $this->softDeleteTimestamp()]);
        } finally {
            $this->removedScopes = $savedRemoved;
        }
    }

    /**
     * 恢复：批量恢复已删行（软删列置回默认值，默认 null 即未删）。
     */
    public function restore(): int
    {
        $softDelete = $this->modelClass::getSoftDelete();
        if (!$softDelete || !$softDelete->active()) {
            throw new \yuandian\Database\Exceptions\DbException('模型未启用软删除，无法恢复');
        }

        $savedRemoved = $this->removedScopes;
        $this->removedScopes[] = 'softDelete';
        try {
            return $this->update([$softDelete->column => $softDelete->default]);
        } finally {
            $this->removedScopes = $savedRemoved;
        }
    }

    /**
     * 查询含已删行：跳过软删过滤。
     */
    public function withTrashed(): static
    {
        return $this->withoutGlobalScope('softDelete');
    }

    /**
     * 仅查已删行：跳过软删过滤并限定软删列非空。
     */
    public function onlyTrashed(): static
    {
        $softDelete = $this->modelClass::getSoftDelete();
        $column = $softDelete?->column ?? 'deleted_time';

        return $this->withTrashed()->whereNotNull($column);
    }

    /**
     * 全局作用域：注入软删过滤（默认查询排除已删行）。
     *
     * 由 Db 层终端方法（Query/MongoQuery 的 find/select/update/delete 等）动态派发调用，
     * 每次操作恰好一次；宿主类无需（也不应）重复调用。
     */
    protected function applyGlobalScopes(): void
    {
        if ($this->withoutScopes) {
            return;
        }

        $softDelete = $this->modelClass::getSoftDelete();
        if (!$softDelete || !$softDelete->active() || in_array('softDelete', $this->removedScopes, true)) {
            return;
        }

        // 幂等：软删过滤可能被宿主覆写方法与 Query 父类双重触发，已注入则跳过
        if (!$this->state->where->hasCondition($softDelete->column, 'NULL')) {
            $this->state->where->add('AND', new WhereCondition($softDelete->column, 'NULL', ''));
        }
    }

    /**
     * 软删时间戳：按软删列默认值类型生成（int 列→秒级时间戳，其他→日期时间字符串）。
     */
    protected function softDeleteTimestamp(): int|string
    {
        $softDelete = $this->modelClass::getSoftDelete();

        return is_int($softDelete?->default) ? time() : date('Y-m-d H:i:s');
    }
}