<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\Cast;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Cast\JsonCaster;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_json_cast')]
class JsonCastModel extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public string $name = '';

    #[Cast(JsonCaster::class, castTo: ProductOptions::class)]
    public ?ProductOptions $options = null;
}
