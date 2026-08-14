<?php

declare(strict_types=1);

namespace yuandian\Database\Facade;

use yuandian\Container\Facade;
use yuandian\Database\DbManager;

/**
 * @see DbManager
 * @mixin DbManager
 */
class DB extends Facade
{
    protected static function getFacadeClass(): string
    {
        return DbManager::class;
    }
}
