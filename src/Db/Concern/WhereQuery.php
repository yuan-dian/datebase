<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Concern;

use Closure;
use yuandian\Database\Db\Raw;

trait WhereQuery
{
    public function where(Closure|string|array $field, mixed $operator = null, mixed $value = null): static
    {
        return $this->parseWhereExp('AND', $field, $operator, $value);
    }

    public function orWhere(Closure|string $field, mixed $operator = null, mixed $value = null): static
    {
        return $this->parseWhereExp('OR', $field, $operator, $value);
    }

    public function whereIn(string $field, array $values): static
    {
        $this->options['where']['AND'][] = [$field, 'IN', $values];
        return $this;
    }

    public function whereNotIn(string $field, array $values): static
    {
        $this->options['where']['AND'][] = [$field, 'NOT IN', $values];
        return $this;
    }

    public function whereNull(string $field): static
    {
        $this->options['where']['AND'][] = [$field, 'NULL', ''];
        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $this->options['where']['AND'][] = [$field, 'NOT NULL', ''];
        return $this;
    }

    public function whereBetween(string $field, mixed $min, mixed $max): static
    {
        $this->options['where']['AND'][] = [$field, 'BETWEEN', [$min, $max]];
        return $this;
    }

    public function whereLike(string $field, string $value): static
    {
        $this->options['where']['AND'][] = [$field, 'LIKE', $value];
        return $this;
    }

    public function whereRaw(string $condition, array $bind = []): static
    {
        $this->options['where']['AND'][] = new Raw($condition, $bind);
        return $this;
    }

    public function orWhereRaw(string $condition, array $bind = []): static
    {
        $this->options['where']['OR'][] = new Raw($condition, $bind);
        return $this;
    }

    public function whereExists(Closure|string $condition, string $logic = 'AND'): static
    {
        $this->options['where'][strtoupper($logic)][] = ['', 'EXISTS', $condition];
        return $this;
    }

    public function whereNotExists(Closure|string $condition, string $logic = 'AND'): static
    {
        $this->options['where'][strtoupper($logic)][] = ['', 'NOT EXISTS', $condition];
        return $this;
    }

    public function whereColumn(string $field1, string $operator, ?string $field2 = null, string $logic = 'AND'): static
    {
        if (is_null($field2)) {
            $field2 = $operator;
            $operator = '=';
        }

        $this->options['where'][strtoupper($logic)][] = [$field1, 'COLUMN', [$operator, $field2]];
        return $this;
    }

    protected function parseWhereExp(string $logic, Closure|string|array $field, mixed $operator, mixed $value): static
    {
        $logic = strtoupper($logic);

        // 嵌套条件组
        if ($field instanceof Closure) {
            $sub = $this->newSubQuery();
            $field($sub);
            $this->options['where'][$logic][] = function ($query) use ($sub) {
                $query->options['where'] = array_merge(
                    $query->options['where'] ?? [],
                    $sub->options['where'] ?? []
                );
            };
            return $this;
        }

        // 批量条件
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $this->where($k, '=', $v);
            }
            return $this;
        }

        // 省略运算符
        if (func_num_args() === 3) {
            $value = $operator;
            $operator = '=';
        }

        // IS NULL
        if ($value === null) {
            $this->options['where'][$logic][] = [$field, 'NULL', ''];
            return $this;
        }

        $this->options['where'][$logic][] = [$field, $operator, $value];
        return $this;
    }
}
