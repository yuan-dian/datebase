<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\HasMany;
use yuandian\Database\Attribute\HasOne;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_user')]
class User extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';

    public string $email = '';

    public int $age = 0;

    #[HasOne(Profile::class, 'test_user_id', 'id')]
    public ?Profile $profile = null;

    #[HasMany(Post::class, 'test_user_id', 'id')]
    public ?array $posts = null;
}
