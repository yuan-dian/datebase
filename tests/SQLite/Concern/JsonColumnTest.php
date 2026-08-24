<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Concern;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Facade\DB;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\JsonModel;
use yuandian\Database\Tests\Fixture\Model\JsonCastModel;
use yuandian\Database\Tests\Fixture\Model\ProductOptions;

class JsonColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();

        $this->createTable('CREATE TABLE test_json (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            settings TEXT DEFAULT NULL,
            metadata TEXT DEFAULT NULL
        )');
        $this->createTable('CREATE TABLE test_json_cast (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            options TEXT DEFAULT NULL
        )');
    }

    public function testInsertSerializesArrayToJson(): void
    {
        $model = new JsonModel();
        $model->name = 'Test';
        $model->settings = ['theme' => 'dark', 'lang' => 'en'];
        $model->insert();

        $conn = DB::connect();
        $rows = $conn->query('SELECT settings FROM test_json WHERE id = ?', [$model->id]);
        $this->assertIsString($rows[0]['settings']);
        $decoded = json_decode($rows[0]['settings'], true);
        $this->assertSame('dark', $decoded['theme']);
        $this->assertSame('en', $decoded['lang']);
    }

    public function testSelectDeserializesJsonToArray(): void
    {
        $model = new JsonModel();
        $model->name = 'Test';
        $model->settings = ['key' => 'value'];
        $model->insert();

        $found = JsonModel::where('id', '=', $model->id)->find();
        $this->assertIsArray($found->settings);
        $this->assertSame('value', $found->settings['key']);
    }

    public function testNullJsonColumnReturnsEmptyArray(): void
    {
        $model = new JsonModel();
        $model->name = 'Null Test';
        $model->insert();

        $found = JsonModel::where('id', '=', $model->id)->find();
        // #[JsonColumn] without castTo returns [] for null/empty DB values
        $this->assertIsArray($found->settings);
        $this->assertCount(0, $found->settings);
    }

    public function testUpdateSerializesNewJson(): void
    {
        $model = new JsonModel();
        $model->name = 'Update Test';
        $model->settings = ['a' => 1];
        $model->insert();

        $model->settings = ['b' => 2, 'c' => 3];
        $model->save();

        $found = JsonModel::where('id', '=', $model->id)->find();
        $this->assertIsArray($found->settings);
        $this->assertSame(2, $found->settings['b']);
        $this->assertSame(3, $found->settings['c']);
        $this->assertArrayNotHasKey('a', $found->settings);
    }

    public function testCastToClassDeserializesObject(): void
    {
        $model = new JsonCastModel();
        $model->name = 'Cast Test';
        $model->options = new ProductOptions(color: 'red', size: 42);
        $model->insert();

        $found = JsonCastModel::where('id', '=', $model->id)->find();
        $this->assertInstanceOf(ProductOptions::class, $found->options);
        $this->assertSame('red', $found->options->color);
        $this->assertSame(42, $found->options->size);
    }

    public function testEmptyArrayRoundTrip(): void
    {
        $model = new JsonModel();
        $model->name = 'Empty';
        $model->settings = [];
        $model->insert();

        $found = JsonModel::where('id', '=', $model->id)->find();
        $this->assertIsArray($found->settings);
        $this->assertCount(0, $found->settings);
    }

    public function testNestedArrayRoundTrip(): void
    {
        $nested = ['users' => [['name' => 'Alice', 'roles' => ['admin', 'editor']]]];
        $model = new JsonModel();
        $model->name = 'Nested';
        $model->settings = $nested;
        $model->insert();

        $found = JsonModel::where('id', '=', $model->id)->find();
        $this->assertSame('Alice', $found->settings['users'][0]['name']);
        $this->assertSame(['admin', 'editor'], $found->settings['users'][0]['roles']);
    }
}
