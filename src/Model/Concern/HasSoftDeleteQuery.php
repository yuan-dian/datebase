<?php

declare(strict_types=1);

namespace yuandian\Database\Model\Concern;

use yuandian\Database\Db\Expression\WhereCondition;

trait HasSoftDeleteQuery
{
    protected bool $forceDelete = false;

    public function force(): static
    {
        $this->forceDelete = true;

        return $this;
    }

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

            return $this->update([$softDelete->column => $softDelete->deletedValue ?? $this->softDeleteTimestamp()]);
        } finally {
            $this->removedScopes = $savedRemoved;
        }
    }

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

    public function withTrashed(): static
    {
        return $this->withoutGlobalScope('softDelete');
    }

    /**
     * 仅查已删行：跳过软删过滤并限定软删列 ≠ $default。
     */
    public function onlyTrashed(): static
    {
        $softDelete = $this->modelClass::getSoftDelete();
        $column = $softDelete?->column ?? 'deleted_time';
        $default = $softDelete?->default;

        $query = $this->withTrashed();

        if ($default === null) {
            return $query->whereNotNull($column);
        }

        return $query->where($column, '<>', $default);
    }

    /**
     * 全局作用域：遍历注册的 Scope + 软删除过滤。
     *
     * 由 Db 层终端方法（Query::find/select/update/delete）统一调用一次。
     */
    protected function applyGlobalScopes(): void
    {
        if ($this->withoutScopes) {
            return;
        }

        $globalScopes = $this->modelClass::getGlobalScopes();
        foreach ($globalScopes as $identifier => $scope) {
            if (in_array($identifier, $this->removedScopes, true)) {
                continue;
            }
            $scope->apply($this, $this->modelClass);
        }

        if (!in_array('softDelete', $this->removedScopes, true)) {
            $softDelete = $this->modelClass::getSoftDelete();
            if ($softDelete && $softDelete->active()) {
                $column = $softDelete->column;
                $default = $softDelete->default;

                if ($default === null) {
                    if (!$this->state->where->hasCondition($column, 'NULL')) {
                        $this->state->where->add('AND', new WhereCondition($column, 'NULL', ''));
                    }
                } else {
                    if (!$this->state->where->hasCondition($column, '=', $default)) {
                        $this->state->where->add('AND', new WhereCondition($column, '=', $default));
                    }
                }
            }
        }
    }

    protected function softDeleteTimestamp(): int|string
    {
        $softDelete = $this->modelClass::getSoftDelete();

        return is_int($softDelete?->default) ? time() : date('Y-m-d H:i:s');
    }
}
