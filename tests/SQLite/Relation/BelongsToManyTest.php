<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Relation;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\Tag;
use yuandian\Database\Tests\Fixture\Model\TagUser;
use yuandian\Database\Tests\Fixture\Model\UserTag;

class BelongsToManyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->createTable('CREATE TABLE test_tag_user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');
        $this->createTable('CREATE TABLE test_tag (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT ""
        )');
        $this->createTable('CREATE TABLE test_user_tag (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_user_id INTEGER NOT NULL DEFAULT 0,
            test_tag_id INTEGER NOT NULL DEFAULT 0
        )');
    }

    private function seedData(): TagUser
    {
        $user = new TagUser();
        $user->name = 'Alice';
        $user->insert();

        $tag1 = new Tag();
        $tag1->name = 'PHP';
        $tag1->insert();

        $tag2 = new Tag();
        $tag2->name = 'MySQL';
        $tag2->insert();

        $pivot1 = new UserTag();
        $pivot1->testUserId = $user->id;
        $pivot1->testTagId = $tag1->id;
        $pivot1->insert();

        $pivot2 = new UserTag();
        $pivot2->testUserId = $user->id;
        $pivot2->testTagId = $tag2->id;
        $pivot2->insert();

        return $user;
    }

    public function testBelongsToManyLazyLoad(): void
    {
        $user = $this->seedData();
        $user->load('tags');

        $this->assertIsArray($user->tags);
        $this->assertCount(2, $user->tags);
        $names = array_map(fn($t) => $t->name, $user->tags);
        $this->assertContains('PHP', $names);
        $this->assertContains('MySQL', $names);
    }

    public function testBelongsToManyEagerLoad(): void
    {
        $this->seedData();

        $users = TagUser::with('tags')->select();

        $this->assertCount(1, $users);
        $this->assertIsArray($users[0]->tags);
        $this->assertCount(2, $users[0]->tags);
    }

    public function testBelongsToManyReturnsEmptyArrayWhenNoTags(): void
    {
        $user = new TagUser();
        $user->name = 'No Tags';
        $user->insert();

        $user->load('tags');

        $this->assertIsArray($user->tags);
        $this->assertCount(0, $user->tags);
    }

    public function testBelongsToManyMultipleUsers(): void
    {
        $user1 = new TagUser();
        $user1->name = 'Alice';
        $user1->insert();

        $user2 = new TagUser();
        $user2->name = 'Bob';
        $user2->insert();

        $tag1 = new Tag();
        $tag1->name = 'PHP';
        $tag1->insert();

        $tag2 = new Tag();
        $tag2->name = 'MySQL';
        $tag2->insert();

        $pivot1 = new UserTag();
        $pivot1->testUserId = $user1->id;
        $pivot1->testTagId = $tag1->id;
        $pivot1->insert();

        $pivot2 = new UserTag();
        $pivot2->testUserId = $user1->id;
        $pivot2->testTagId = $tag2->id;
        $pivot2->insert();

        $pivot3 = new UserTag();
        $pivot3->testUserId = $user2->id;
        $pivot3->testTagId = $tag1->id;
        $pivot3->insert();

        $users = TagUser::with('tags')->order('id', 'ASC')->select();

        $this->assertCount(2, $users);
        $this->assertCount(2, $users[0]->tags);
        $this->assertCount(1, $users[1]->tags);
    }
}
