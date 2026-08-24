<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\BelongsTo;
use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_post')]
class Post extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $testUserId = 0;

    public string $title = '';

    public string $body = '';

    #[BelongsTo(User::class, 'test_user_id', 'id')]
    public ?User $user = null;

    #[HasMany(Comment::class, 'test_post_id', 'id')]
    public ?array $comments = null;
}
