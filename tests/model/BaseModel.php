<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2026/4/30
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace yuandian\Database\Tests\model;

use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Model\Model;

#[SoftDelete]
class BaseModel extends Model
{

}