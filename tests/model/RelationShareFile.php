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

use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('share_file')]
class RelationShareFile extends Model
{
    #[TableId(IdType::ASSIGN_ID)]
    public int $id = 0;
    public int $shareId = 0;
    public int $fileId = 0;
    public int $userId = 0;
}