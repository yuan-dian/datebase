<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2026/8/17
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace yuandian\Database\Tests\model;

use yuandian\Database\Attribute\AutoWriteTime;
use yuandian\Database\Attribute\JsonColumn;
use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
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
    #[JsonColumn]
    public ?array $tags = null;
    public ?string $createTime = null;
    public ?string $updateTime = null;
}