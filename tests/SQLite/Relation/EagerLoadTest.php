<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Relation;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\User;
use yuandian\Database\Tests\Fixture\Model\Post;
use yuandian\Database\Tests\Fixture\Model\Comment;

class EagerLoadTest extends TestCase
{
    use RefreshDatabase;

    private array $sqls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();
        $this->sqls = [];

        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL DEFAULT "",
            age INTEGER NOT NULL DEFAULT 0
        )');
        $this->createTable('CREATE TABLE test_post (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_user_id INTEGER NOT NULL DEFAULT 0,
            title TEXT NOT NULL DEFAULT "",
            body TEXT NOT NULL DEFAULT ""
        )');
        $this->createTable('CREATE TABLE test_comment (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_post_id INTEGER NOT NULL DEFAULT 0,
            content TEXT NOT NULL DEFAULT ""
        )');

        DB::listen('sql', function (string $sql): void {
            $this->sqls[] = $sql;
        });

        $conn = DB::connect();
        $conn->execute("INSERT INTO test_user (name, email, age) VALUES ('Alice', 'a@test.com', 25), ('Bob', 'b@test.com', 30), ('Carol', 'c@test.com', 35)");
        $conn->execute("INSERT INTO test_post (test_user_id, title, body) VALUES (1, 'p1', 'b1'), (1, 'p2', 'b2'), (2, 'p3', 'b3')");
        $conn->execute("INSERT INTO test_comment (test_post_id, content) VALUES (1, 'c1'), (1, 'c2'), (2, 'c3')");
    }

    public function testNestedEagerLoadSqlCount(): void
    {
        $this->sqls = [];
        $users = User::with('posts.comments')->select();

        $this->assertCount(3, $this->sqls, 'No N+1: exactly 3 SQL for 3-level eager load');
        $this->assertCount(3, $users);
        $this->assertCount(2, $users[0]->posts[0]->comments);
        $this->assertSame('c1', $users[0]->posts[0]->comments[0]->content);
        $this->assertSame('c2', $users[0]->posts[0]->comments[1]->content);
        $this->assertCount(1, $users[0]->posts[1]->comments);
    }

    public function testNestedEagerLoadDeduplication(): void
    {
        $this->sqls = [];
        $users = User::with('posts', 'posts.comments')->select();

        $this->assertCount(3, $this->sqls, 'Dedup: posts not loaded twice');
        $this->assertCount(2, $users[0]->posts[0]->comments);
    }

    public function testBaselineWithoutNesting(): void
    {
        $this->sqls = [];
        $users = User::with('posts')->select();

        $this->assertCount(2, $this->sqls, 'Baseline: user + post only, 2 SQL');
        $this->assertCount(2, $users[0]->posts);
        $this->assertNull($users[0]->posts[0]->comments);
    }

    public function testFindWithNestedEagerLoad(): void
    {
        $this->sqls = [];
        $user = User::with('posts.comments')->find();

        $this->assertCount(3, $this->sqls, 'find() nested eager: still 3 SQL');
        $this->assertNotNull($user);
        $this->assertCount(2, $user->posts[0]->comments);
        $this->assertSame('c1', $user->posts[0]->comments[0]->content);
    }

    public function testFindWithDeduplication(): void
    {
        $this->sqls = [];
        $user = User::with('posts', 'posts.comments')->find();

        $this->assertCount(3, $this->sqls, 'find() dedup: still 3 SQL');
        $this->assertNotNull($user);
        $this->assertCount(2, $user->posts[0]->comments);
    }

    public function testUnknownRelationSkippedGracefully(): void
    {
        $this->sqls = [];
        $users = User::with('posts.comments.missing')->select();

        $this->assertCount(3, $this->sqls, 'Unknown relation skipped, still 3 SQL');
        $this->assertCount(2, $users[0]->posts[0]->comments);
    }
}
