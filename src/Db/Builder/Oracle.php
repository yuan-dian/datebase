<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Builder;

use yuandian\Database\Db\Expression\Raw;

class Oracle extends Mysql
{
    public function wrap(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        if ($value instanceof Raw) {
            return $value->getValue();
        }

        $value = strtoupper($value);

        if (str_contains($value, '.')) {
            [$t, $c] = explode('.', $value, 2);
            return '"' . str_replace('"', '""', $t) . '"."' . str_replace('"', '""', $c) . '"';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function parseLimit(?int $limit, ?int $offset): string
    {
        // Oracle 12c+ 分页语法要求 OFFSET 在 FETCH 之前：
        // OFFSET n ROWS FETCH NEXT m ROWS ONLY（顺序颠倒会报 ORA-00933）
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('limit 不能为负数，当前值：' . $limit);
        }
        $sql = '';
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset . ' ROWS';
        }
        if ($limit !== null) {
            $sql .= ' FETCH NEXT ' . $limit . ' ROWS ONLY';
        }
        return $sql;
    }
}
