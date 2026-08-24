<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_tag')]
class Tag extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public string $name = '';
}
