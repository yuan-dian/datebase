<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Relation;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\Author;
use yuandian\Database\Tests\Fixture\Model\Book;
use yuandian\Database\Tests\Fixture\Model\BookAssignment;

class ThroughRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->createTable('CREATE TABLE test_author (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');
        $this->createTable('CREATE TABLE test_book (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL DEFAULT ""
        )');
        $this->createTable('CREATE TABLE test_book_assignment (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_author_id INTEGER NOT NULL DEFAULT 0,
            test_book_id INTEGER NOT NULL DEFAULT 0
        )');
    }

    private function seedData(): Author
    {
        $author = new Author();
        $author->name = 'Alice';
        $author->insert();

        $book1 = new Book();
        $book1->title = 'Book One';
        $book1->insert();

        $book2 = new Book();
        $book2->title = 'Book Two';
        $book2->insert();

        $assignment1 = new BookAssignment();
        $assignment1->testAuthorId = $author->id;
        $assignment1->testBookId = $book1->id;
        $assignment1->insert();

        $assignment2 = new BookAssignment();
        $assignment2->testAuthorId = $author->id;
        $assignment2->testBookId = $book2->id;
        $assignment2->insert();

        return $author;
    }

    public function testHasOneThroughLazyLoad(): void
    {
        $author = $this->seedData();
        $author->load('latestBook');

        $this->assertNotNull($author->latestBook);
        $this->assertSame('Book One', $author->latestBook->title);
    }

    public function testHasManyThroughLazyLoad(): void
    {
        $author = $this->seedData();
        $author->load('books');

        $this->assertIsArray($author->books);
        $this->assertCount(2, $author->books);
        $titles = array_map(fn($b) => $b->title, $author->books);
        $this->assertContains('Book One', $titles);
        $this->assertContains('Book Two', $titles);
    }

    public function testHasManyThroughEagerLoad(): void
    {
        $this->seedData();

        $authors = Author::with('books')->select();

        $this->assertCount(1, $authors);
        $this->assertIsArray($authors[0]->books);
        $this->assertCount(2, $authors[0]->books);
    }

    public function testHasOneThroughReturnsNullWhenNoMatch(): void
    {
        $author = new Author();
        $author->name = 'No Books';
        $author->insert();

        $author->load('latestBook');

        $this->assertNull($author->latestBook);
    }

    public function testHasManyThroughReturnsEmptyArrayWhenNoMatch(): void
    {
        $author = new Author();
        $author->name = 'No Books';
        $author->insert();

        $author->load('books');

        $this->assertIsArray($author->books);
        $this->assertCount(0, $author->books);
    }
}
