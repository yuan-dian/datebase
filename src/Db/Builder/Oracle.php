<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Builder;

use yuandian\Database\Db\Builder;
use yuandian\Database\Db\Raw;

class Oracle extends Builder
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
        $sql = '';
        if ($limit !== null) {
            $sql .= ' FETCH FIRST ' . $limit . ' ROWS ONLY';
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset . ' ROWS';
        }
        return $sql;
    }
}
