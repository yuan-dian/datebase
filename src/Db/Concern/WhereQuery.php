<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Concern;

use Closure;
use InvalidArgumentException;
use yuandian\Database\Db\Raw;

trait WhereQuery
{
    /**
     * 字段名转换钩子：Db 层原样返回；模型层（ModelQuery）覆写为 camel→snake。
     *
     * 用于 where/whereIn/whereNull 等所有字段接受入口，保证模型层可用属性名查询。
     */
    protected function convertFieldName(string $field): string
    {
        return $field;
    }

    /**
     * 基础条件：字段 + 任意比较运算符 + 值。
     *
     * 值域为显式联合（标量/null/数组/Raw/Closure 子查询），不接受任意对象——
     * 保证 TypePHP AOT 编译期可校验，无效类型直接编译报错。
     * 固定运算符场景优先使用专用方法：whereEqual/whereIn/whereNull/whereLike/
     * whereBetween/whereExists/whereColumn/whereRaw。
     *
     * @param string|int|float|bool|null|array|Raw|Closure $value
     */
    public function where(string $field, string $operator, string|int|float|bool|null|array|Raw|Closure $value): static
    {
        return $this->parseWhereExp('AND', $field, $operator, $value);
    }

    /**
     * 或条件：与 where 相同的类型化签名。
     *
     * @param string|int|float|bool|null|array|Raw|Closure $value
     */
    public function orWhere(string $field, string $operator, string|int|float|bool|null|array|Raw|Closure $value): static
    {
        return $this->parseWhereExp('OR', $field, $operator, $value);
    }

    /**
     * 等值条件：where('field', '=', $value) 的类型化替代。
     *
     * null 值转 IS NULL，与 where('field', '=', null) 行为一致。
     */
    public function whereEqual(string $field, string|int|float|bool|null $value): static
    {
        return $this->parseEqual('AND', $field, $value);
    }

    /**
     * 或等值条件。
     */
    public function orWhereEqual(string $field, string|int|float|bool|null $value): static
    {
        return $this->parseEqual('OR', $field, $value);
    }

    /**
     * 批量等值条件：键值对形式，键为字段名（支持属性名），值为标量或 null。
     *
     * 键值对均为 AND 连接。数组值（IN）不支持——请使用 whereIn()/whereNotIn()，
     * 避免 where(['id' => [1,2]]) 这类易混淆写法。
     *
     * @param array<string, string|int|float|bool|null> $conditions
     */
    public function whereMap(array $conditions): static
    {
        foreach ($conditions as $field => $value) {
            if (is_array($value) || is_object($value)) {
                throw new InvalidArgumentException(sprintf(
                    'whereMap 仅支持标量值或 null（字段 %s 的值为 %s），IN 条件请使用 whereIn()/whereNotIn()',
                    (string)$field,
                    is_object($value) ? $value::class : 'array'
                ));
            }
            $this->parseEqual('AND', (string)$field, $value);
        }
        return $this;
    }

    /**
     * 条件分组：闭包内构建子条件组，整体作为一个括号包裹的条件。
     *
     * @param Closure $callback
     */
    public function whereGroup(Closure $callback): static
    {
        return $this->parseWhereGroup('AND', $callback);
    }

    /**
     * 或条件分组。
     *
     * @param Closure $callback
     */
    public function orWhereGroup(Closure $callback): static
    {
        return $this->parseWhereGroup('OR', $callback);
    }

    public function whereIn(string $field, array $values): static
    {
        $this->options['where']['AND'][] = [$this->convertFieldName($field), 'IN', $values];
        return $this;
    }

    public function whereNotIn(string $field, array $values): static
    {
        $this->options['where']['AND'][] = [$this->convertFieldName($field), 'NOT IN', $values];
        return $this;
    }

    public function whereNull(string $field): static
    {
        $this->options['where']['AND'][] = [$this->convertFieldName($field), 'NULL', ''];
        return $this;
    }

    public function whereNotNull(string $field): static
    {
        $this->options['where']['AND'][] = [$this->convertFieldName($field), 'NOT NULL', ''];
        return $this;
    }

    public function whereBetween(string $field, string|int|float $min, string|int|float $max): static
    {
        $this->options['where']['AND'][] = [$this->convertFieldName($field), 'BETWEEN', [$min, $max]];
        return $this;
    }

    public function whereLike(string $field, string $value): static
    {
        $this->options['where']['AND'][] = [$this->convertFieldName($field), 'LIKE', $value];
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

        $this->options['where'][strtoupper($logic)][] = [
            $this->convertFieldName($field1),
            'COLUMN',
            [$operator, $this->convertFieldName($field2)],
        ];
        return $this;
    }

    /**
     * 通用条件存储：AND/OR + 运算符 + 值。
     *
     * @param string|int|float|bool|null|array|Raw|Closure $value
     */
    protected function parseWhereExp(string $logic, string $field, string $operator, string|int|float|bool|null|array|Raw|Closure $value): static
    {
        $logic = strtoupper($logic);

        // IS NULL：null 值统一转 NULL 条件（无论运算符）
        if ($value === null) {
            $this->options['where'][$logic][] = [$this->convertFieldName($field), 'NULL', ''];
            return $this;
        }

        $this->options['where'][$logic][] = [$this->convertFieldName($field), $operator, $value];
        return $this;
    }

    /**
     * 等值条件存储：null 转 IS NULL。
     */
    protected function parseEqual(string $logic, string $field, string|int|float|bool|null $value): static
    {
        $logic = strtoupper($logic);

        if ($value === null) {
            $this->options['where'][$logic][] = [$this->convertFieldName($field), 'NULL', ''];
            return $this;
        }

        $this->options['where'][$logic][] = [$this->convertFieldName($field), '=', $value];
        return $this;
    }

    /**
     * 条件分组存储：闭包构建子查询，Builder 解析时递归合并。
     *
     * @param Closure $callback
     */
    protected function parseWhereGroup(string $logic, Closure $callback): static
    {
        $logic = strtoupper($logic);

        $sub = $this->newSubQuery();
        $callback($sub);
        // 闭包直接返回子条件组：Builder 解析时递归合并（避免引用参数，兼容 TypePHP AOT）
        $this->options['where'][$logic][] = static function () use ($sub): array {
            return $sub->options['where'] ?? [];
        };
        return $this;
    }
}
