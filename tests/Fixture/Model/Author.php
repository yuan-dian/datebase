<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\HasOneThrough;
use yuandian\Database\Attribute\HasManyThrough;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_author')]
class Author extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;
    public string $name = '';

    #[HasOneThrough(Book::class, BookAssignment::class, null, 'test_author_id', 'id', 'test_book_id')]
    public ?Book $latestBook = null;

    #[HasManyThrough(Book::class, BookAssignment::class, null, 'test_author_id', 'id', 'test_book_id')]
    public ?array $books = null;
}
