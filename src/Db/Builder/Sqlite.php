<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Builder;

use yuandian\Database\Db\Expression\Raw;

class Sqlite extends Mysql
{
    public function wrap(string|Raw $value): string
    {
        if ($value === '*') {
            return $value;
        }

        if ($value instanceof Raw) {
            return $value->getValue();
        }

        if (str_contains($value, '.')) {
            [$t, $c] = explode('.', $value, 2);
            return '"' . str_replace('"', '""', $t) . '"."' . str_replace('"', '""', $c) . '"';
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
