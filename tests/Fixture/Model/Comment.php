<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\BelongsTo;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_comment')]
class Comment extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $testPostId = 0;

    public string $content = '';

    #[BelongsTo(Post::class, 'test_post_id', 'id')]
    public ?Post $post = null;
}
