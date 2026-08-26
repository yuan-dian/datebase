<?php

declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_soft_default_user')]
#[SoftDelete(column: 'deleted_time', default: '0', deletedValue: '1')]
class SoftDeleteDefaultUser extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';
}
