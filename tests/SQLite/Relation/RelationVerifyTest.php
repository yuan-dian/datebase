<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Relation;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\Post;
use yuandian\Database\Tests\Fixture\Model\User;

class RelationVerifyTest extends TestCase
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
        $this->createTable('CREATE TABLE test_post (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_user_id INTEGER NOT NULL DEFAULT 0,
            title TEXT NOT NULL DEFAULT "",
            body TEXT NOT NULL DEFAULT ""
        )');
    }

    private function seedData(): User
    {
        $user = new User();
        $user->name = 'Alice';
        $user->email = 'alice@test.com';
        $user->age = 25;
        $user->insert();

        $post = new Post();
        $post->testUserId = $user->id;
        $post->title = 'First Post';
        $post->body = 'Hello';
        $post->insert();

        return $user;
    }

    public function testCamelCaseWhereConversion(): void
    {
        $this->seedData();

        $found = Post::where('testUserId', '=', 1)->find();
        $this->assertNotNull($found);
        $this->assertSame('First Post', $found->title);
    }

    public function testCamelCaseWhereBuildSql(): void
    {
        $sql = Post::where('testUserId', '=', 1)->buildSql();
        $this->assertStringContainsString('test_user_id', $sql);
    }

    public function testCamelCaseOrderBuildSql(): void
    {
        $sql = Post::where('id', '>', 0)->order('testUserId', 'desc')->buildSql();
        $this->assertStringContainsString('test_user_id', $sql);
        $this->assertStringContainsString('DESC', $sql);
    }

    public function testCamelCaseGroupByBuildSql(): void
    {
        $sql = Post::where('id', '>', 0)->groupBy('testUserId')->buildSql();
        $this->assertStringContainsString('test_user_id', $sql);
    }

    public function testCamelCaseFieldBuildSql(): void
    {
        $sql = Post::where('id', '>', 0)->field(['testUserId', 'title'])->buildSql();
        $this->assertStringContainsString('test_user_id', $sql);
        $this->assertStringContainsString('title', $sql);
    }

    public function testDbLayerDoesNotConvertCamelCase(): void
    {
        $sql = DB::table('test_post')->where('testUserId', '=', 1)->buildSql();
        $this->assertStringContainsString('testUserId', $sql);
        $this->assertStringNotContainsString('test_user_id', $sql);
    }

    public function testToJsonSerialization(): void
    {
        $user = $this->seedData();

        $json = $user->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Alice', $decoded['name']);
        $this->assertSame('alice@test.com', $decoded['email']);
    }

    public function testToStringReturnsJson(): void
    {
        $user = $this->seedData();

        $str = (string)$user;
        $decoded = json_decode($str, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Alice', $decoded['name']);
    }

    public function testColumnMapExcludesRelationProperties(): void
    {
        $map = Post::getColumnMap();

        $this->assertArrayHasKey('title', $map);
        $this->assertArrayHasKey('body', $map);
        $this->assertArrayHasKey('testUserId', $map);
        $this->assertArrayNotHasKey('user', $map);
        $this->assertArrayNotHasKey('comments', $map);
    }

    public function testBuildSqlContainsFrom(): void
    {
        $sql = Post::where('id', '>', 0)->buildSql();
        $this->assertIsString($sql);
        $this->assertStringContainsString('FROM', $sql);
    }

    public function testIncDecCamelCaseConversion(): void
    {
        $this->seedData();

        $incQ = User::where('id', '=', 1)->inc('age', 1);
        $incState = $incQ->getState();
        $this->assertArrayHasKey('age', $incState->data);
    }

    public function testLoadAfterSaveDoesNotCorruptData(): void
    {
        $this->seedData();

        $post = Post::where('id', '=', 1)->find();
        $post->load('user');
        $saveOk = $post->save();

        $this->assertTrue($saveOk);
        $this->assertNotNull($post->user);
    }
}
