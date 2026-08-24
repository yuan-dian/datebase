<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Relation;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\User;
use yuandian\Database\Tests\Fixture\Model\Post;
use yuandian\Database\Tests\Fixture\Model\Comment;
use yuandian\Database\Tests\Fixture\Model\Profile;

class RelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->createTable('CREATE TABLE test_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL DEFAULT "",
            age INTEGER NOT NULL DEFAULT 0
        )');
        $this->createTable('CREATE TABLE test_profile (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_user_id INTEGER NOT NULL DEFAULT 0,
            bio TEXT NOT NULL DEFAULT ""
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
    }

    private function seedData(): User
    {
        $user = new User();
        $user->name = 'Alice';
        $user->email = 'alice@test.com';
        $user->age = 25;
        $user->insert();

        $profile = new Profile();
        $profile->testUserId = $user->id;
        $profile->bio = 'Alice bio';
        $profile->insert();

        $post = new Post();
        $post->testUserId = $user->id;
        $post->title = 'First Post';
        $post->body = 'Hello world';
        $post->insert();

        $comment = new Comment();
        $comment->testPostId = $post->id;
        $comment->content = 'Great post!';
        $comment->insert();

        return $user;
    }

    public function testHasOneLazyLoad(): void
    {
        $user = $this->seedData();

        $user->load('profile');

        $this->assertNotNull($user->profile);
        $this->assertSame('Alice bio', $user->profile->bio);
    }

    public function testHasManyLazyLoad(): void
    {
        $user = $this->seedData();

        $user->load('posts');

        $this->assertIsArray($user->posts);
        $this->assertCount(1, $user->posts);
        $this->assertSame('First Post', $user->posts[0]->title);
    }

    public function testBelongsToLazyLoad(): void
    {
        $this->seedData();

        $post = Post::where('id', '=', 1)->find();
        $post->load('user');

        $this->assertNotNull($post->user);
        $this->assertSame('Alice', $post->user->name);
    }

    public function testEagerLoadHasOne(): void
    {
        $this->seedData();

        $users = User::with('profile')->select();

        $this->assertCount(1, $users);
        $this->assertNotNull($users[0]->profile);
        $this->assertSame('Alice bio', $users[0]->profile->bio);
    }

    public function testEagerLoadHasMany(): void
    {
        $this->seedData();

        $users = User::with('posts')->select();

        $this->assertCount(1, $users);
        $this->assertIsArray($users[0]->posts);
        $this->assertCount(1, $users[0]->posts);
    }

    public function testEagerLoadBelongsTo(): void
    {
        $this->seedData();

        $posts = Post::with('user')->select();

        $this->assertCount(1, $posts);
        $this->assertNotNull($posts[0]->user);
        $this->assertSame('Alice', $posts[0]->user->name);
    }

    public function testNestedEagerLoad(): void
    {
        $this->seedData();

        $users = User::with('posts.comments')->select();

        $this->assertCount(1, $users);
        $this->assertIsArray($users[0]->posts);
        $this->assertCount(1, $users[0]->posts);
        $this->assertIsArray($users[0]->posts[0]->comments);
        $this->assertCount(1, $users[0]->posts[0]->comments);
        $this->assertSame('Great post!', $users[0]->posts[0]->comments[0]->content);
    }

    public function testHasManyThroughPostToUser(): void
    {
        $this->seedData();

        $posts = Post::with('user')->select();

        $this->assertCount(1, $posts);
        $this->assertSame('Alice', $posts[0]->user->name);
    }
}
