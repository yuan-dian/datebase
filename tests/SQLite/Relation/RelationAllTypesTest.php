<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Relation;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\User;
use yuandian\Database\Tests\Fixture\Model\Post;
use yuandian\Database\Tests\Fixture\Model\Profile;
use yuandian\Database\Tests\Fixture\Model\Comment;

class RelationAllTypesTest extends TestCase
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

    private function seedData(): array
    {
        $alice = new User();
        $alice->name = 'Alice';
        $alice->email = 'alice@test.com';
        $alice->age = 25;
        $alice->insert();

        $bob = new User();
        $bob->name = 'Bob';
        $bob->email = 'bob@test.com';
        $bob->age = 30;
        $bob->insert();

        $profile = new Profile();
        $profile->testUserId = $alice->id;
        $profile->bio = 'Alice bio';
        $profile->insert();

        $post1 = new Post();
        $post1->testUserId = $alice->id;
        $post1->title = 'Post 1';
        $post1->body = 'Body 1';
        $post1->insert();

        $post2 = new Post();
        $post2->testUserId = $bob->id;
        $post2->title = 'Post 2';
        $post2->body = 'Body 2';
        $post2->insert();

        $comment = new Comment();
        $comment->testPostId = $post1->id;
        $comment->content = 'Great post!';
        $comment->insert();

        return ['alice' => $alice, 'bob' => $bob, 'post1' => $post1, 'post2' => $post2];
    }

    public function testMixedEagerLoadMultipleRelationTypes(): void
    {
        $this->seedData();

        $users = User::with('profile', 'posts')->select();

        $this->assertCount(2, $users);

        $alice = $users[0];
        $this->assertNotNull($alice->profile);
        $this->assertSame('Alice bio', $alice->profile->bio);
        $this->assertIsArray($alice->posts);
        $this->assertCount(1, $alice->posts);

        $bob = $users[1];
        $this->assertNull($bob->profile);
        $this->assertIsArray($bob->posts);
        $this->assertCount(1, $bob->posts);
    }

    public function testFindWithEagerLoad(): void
    {
        $data = $this->seedData();

        $found = User::with('profile', 'posts')->where('id', '=', $data['alice']->id)->find();

        $this->assertNotNull($found);
        $this->assertSame('Alice', $found->name);
        $this->assertNotNull($found->profile);
        $this->assertSame('Alice bio', $found->profile->bio);
        $this->assertCount(1, $found->posts);
        $this->assertSame('Post 1', $found->posts[0]->title);
    }

    public function testEmptyHasOneReturnsNull(): void
    {
        $this->seedData();

        $bob = User::where('id', '=', 2)->find();
        $bob->load('profile');

        $this->assertNull($bob->profile);
    }

    public function testEmptyHasManyReturnsEmptyArray(): void
    {
        $this->seedData();

        $carol = new User();
        $carol->name = 'Carol';
        $carol->email = 'carol@test.com';
        $carol->age = 20;
        $carol->insert();

        $carol->load('posts');

        $this->assertIsArray($carol->posts);
        $this->assertCount(0, $carol->posts);
    }

    public function testEmptyBelongsToReturnsNull(): void
    {
        $this->seedData();

        $post = new Post();
        $post->testUserId = 99999;
        $post->title = 'Orphan';
        $post->body = 'No user';
        $post->insert();

        $post->load('user');

        $this->assertNull($post->user);
    }

    public function testEmptyHasManyThroughReturnsEmptyArray(): void
    {
        $data = $this->seedData();

        $posts = Post::with('user', 'comments')->select();

        $this->assertIsArray($posts);
        $this->assertCount(2, $posts);

        $post1 = $posts[0];
        $this->assertNotNull($post1->user);
        $this->assertSame('Alice', $post1->user->name);
        $this->assertIsArray($post1->comments);
        $this->assertCount(1, $post1->comments);

        $post2 = $posts[1];
        $this->assertNotNull($post2->user);
        $this->assertSame('Bob', $post2->user->name);
        $this->assertIsArray($post2->comments);
        $this->assertCount(0, $post2->comments);
    }
}
