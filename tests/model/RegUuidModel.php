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

#[Table('tmp_reg_uuid')]
class RegUuidModel extends Model
{
    #[TableId(IdType::ASSIGN_UUID)]
    public string $id = '';
    public string $name = '';
}