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

use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('share_base')]
class RelationShareBase extends Model
{
    #[TableId(IdType::ASSIGN_ID)]
    public int $shareId = 0;
    public string $shareCode = '';
    public string $shareName = '';
    public int $userId = 0;

    #[HasMany(RelationShareFile::class, 'share_id')]
    public ?array $shareFiles = null;

    #[HasOne(RelationShareFolder::class, 'share_id')]
    public ?RelationShareFolder $shareFolder = null;

    #[HasManyThrough(RelationResourceFile::class, RelationShareFile::class, 'share_id', 'file_id', 'share_id', 'file_id')]
    public ?array $resourceFiles = null;

    #[HasOneThrough(RelationResourceFolder::class, RelationShareFolder::class, 'share_id', 'folder_id', 'share_id', 'folder_id')]
    public ?RelationResourceFolder $resourceFolder = null;
}