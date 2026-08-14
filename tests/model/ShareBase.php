<?php
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | @copyright (c) 原点 All rights reserved.
// +----------------------------------------------------------------------
// | Author: 原点 <467490186@qq.com>
// +----------------------------------------------------------------------
// | Date: 2026/4/10
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace yuandian\Database\Tests\model;

use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('share_base')]
class ShareBase extends Model
{

    #[TableId(IdType::ASSIGN_ID)]
    public int $shareId;
    public string $shareCode;
    public string $shareName;

    #[HasOne(ShareFile::class, 'share_id')]
    public ?ShareFile $shareFiles;

}