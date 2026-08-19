<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Builder;

use yuandian\Database\Db\Builder;
use yuandian\Database\Db\Raw;

class Mysql extends Builder
{
    protected string $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%UNION%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    protected string $insertSql = 'INSERT INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';

    protected string $updateSql = 'UPDATE %TABLE% SET %SET%%JOIN%%WHERE%%ORDER%%LIMIT% %COMMENT%';

    public function wrap(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        if ($value instanceof Raw) {
            return $value->getValue();
        }

        if (str_contains($value, '.')) {
            [$t, $c] = explode('.', $value, 2);
            return '`' . str_replace('`', '``', $t) . '`.`' . str_replace('`', '``', $c) . '`';
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }
}
