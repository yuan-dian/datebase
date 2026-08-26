<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\Cast;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Cast\JsonCaster;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('tmp_reg_auto')]
#[SoftDelete('deleted_time')]
#[AutoWriteTime]
class RegAutoModel extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public string $name = '';
    public int $status = 0;
    #[Cast(JsonCaster::class)]
    public ?array $tags = null;
    public ?string $createTime = null;
    public ?string $updateTime = null;
}
