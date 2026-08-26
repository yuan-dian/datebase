<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\SQLite\Model;

use PHPUnit\Framework\TestCase;
use yuandian\Database\Tests\Fixture\helpers\RefreshDatabase;
use yuandian\Database\Tests\Fixture\Model\AllTypesModel;
use yuandian\Database\Tests\Fixture\Model\ProductOptions;

class AllTypesCastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSQLite();
        $this->createTable('CREATE TABLE test_all_types (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            int_field INTEGER NOT NULL DEFAULT 0,
            float_field REAL NOT NULL DEFAULT 0.0,
            string_field TEXT NOT NULL DEFAULT "",
            bool_field INTEGER NOT NULL DEFAULT 0,
            nullable_int INTEGER DEFAULT NULL,
            nullable_float REAL DEFAULT NULL,
            nullable_string TEXT DEFAULT NULL,
            nullable_bool INTEGER DEFAULT NULL,
            date_time_field TEXT DEFAULT NULL,
            json_array TEXT DEFAULT NULL,
            json_object TEXT DEFAULT NULL,
            custom_date_format TEXT DEFAULT NULL
        )');
    }

    // Read path

    public function testIntFieldFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, '42', 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame(42, $model->intField);
    }

    public function testFloatFieldFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, '3.14', '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame(3.14, $model->floatField);
    }

    public function testStringFieldFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, 'hello', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame('hello', $model->stringField);
    }

    public function testBoolFieldTrueFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 1],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertTrue($model->boolField);
    }

    public function testBoolFieldFalseFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertFalse($model->boolField);
    }

    public function testNullableIntNullFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertNull($model->nullableInt);
    }

    public function testNullableIntValueFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'nullable_int'], [
            [1, 0, 0.0, '', 0, '100'],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame(100, $model->nullableInt);
    }

    public function testNullableFloatNullFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertNull($model->nullableFloat);
    }

    public function testNullableFloatValueFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'nullable_float'], [
            [1, 0, 0.0, '', 0, '2.5'],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame(2.5, $model->nullableFloat);
    }

    public function testNullableStringNullFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertNull($model->nullableString);
    }

    public function testNullableStringValueFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'nullable_string'], [
            [1, 0, 0.0, '', 0, 'world'],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame('world', $model->nullableString);
    }

    public function testNullableBoolNullFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertNull($model->nullableBool);
    }

    public function testNullableBoolValueFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'nullable_bool'], [
            [1, 0, 0.0, '', 0, '1'],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertTrue($model->nullableBool);
    }

    public function testDateTimeImmutableFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'date_time_field'], [
            [1, 0, 0.0, '', 0, '2026-08-26 10:30:00'],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertInstanceOf(\DateTimeImmutable::class, $model->dateTimeField);
    }

    public function testDateTimeImmutableNullFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertNull($model->dateTimeField);
    }

    public function testJsonArrayFromDb(): void
    {
        $json = json_encode(['foo', 'bar', 'baz']);
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'json_array'], [
            [1, 0, 0.0, '', 0, $json],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame(['foo', 'bar', 'baz'], $model->jsonArray);
    }

    public function testJsonArrayNullFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertSame([], $model->jsonArray);
    }

    public function testJsonObjectFromDb(): void
    {
        $json = json_encode(['color' => 'red', 'size' => 42]);
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'json_object'], [
            [1, 0, 0.0, '', 0, $json],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertInstanceOf(ProductOptions::class, $model->jsonObject);
        $this->assertSame('red', $model->jsonObject->color);
        $this->assertSame(42, $model->jsonObject->size);
    }

    public function testJsonObjectNullFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field'], [
            [1, 0, 0.0, '', 0],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertNull($model->jsonObject);
    }

    public function testCustomDateFormatFromDb(): void
    {
        $this->seedRows('test_all_types', ['id', 'int_field', 'float_field', 'string_field', 'bool_field', 'custom_date_format'], [
            [1, 0, 0.0, '', 0, '2026/08/26'],
        ]);
        $model = AllTypesModel::where('id', '=', 1)->find();
        $this->assertInstanceOf(\DateTimeImmutable::class, $model->customDateFormat);
        $this->assertSame('2026', $model->customDateFormat->format('Y'));
        $this->assertSame('08', $model->customDateFormat->format('m'));
        $this->assertSame('26', $model->customDateFormat->format('d'));
    }

    // Write path

    public function testIntFieldToDb(): void
    {
        $model = new AllTypesModel();
        $model->intField = 100;
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertSame(100, (int) $rows[0]['int_field']);
    }

    public function testFloatFieldToDb(): void
    {
        $model = new AllTypesModel();
        $model->floatField = 2.718;
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertSame(2.718, (float) $rows[0]['float_field']);
    }

    public function testStringFieldToDb(): void
    {
        $model = new AllTypesModel();
        $model->stringField = 'hello';
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertSame('hello', $rows[0]['string_field']);
    }

    public function testBoolFieldToDb(): void
    {
        $model = new AllTypesModel();
        $model->boolField = false;
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertSame(0, (int) $rows[0]['bool_field']);
    }

    public function testNullableIntNullToDb(): void
    {
        $model = new AllTypesModel();
        $model->nullableInt = null;
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertNull($rows[0]['nullable_int']);
    }

    public function testNullableIntValueToDb(): void
    {
        $model = new AllTypesModel();
        $model->nullableInt = 999;
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertSame(999, (int) $rows[0]['nullable_int']);
    }

    public function testDateTimeImmutableToDb(): void
    {
        $model = new AllTypesModel();
        $model->dateTimeField = new \DateTimeImmutable('2026-08-26 10:30:00');
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertIsString($rows[0]['date_time_field']);
    }

    public function testJsonArrayToDb(): void
    {
        $model = new AllTypesModel();
        $model->jsonArray = ['a', 'b', 'c'];
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertIsString($rows[0]['json_array']);
        $decoded = json_decode($rows[0]['json_array'], true);
        $this->assertSame(['a', 'b', 'c'], $decoded);
    }

    public function testJsonObjectToDb(): void
    {
        $model = new AllTypesModel();
        $model->jsonObject = new ProductOptions(color: 'blue', size: 38);
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertIsString($rows[0]['json_object']);
        $decoded = json_decode($rows[0]['json_object'], true);
        $this->assertSame('blue', $decoded['color']);
        $this->assertSame(38, $decoded['size']);
    }

    public function testCustomDateFormatToDb(): void
    {
        $model = new AllTypesModel();
        $model->customDateFormat = new \DateTimeImmutable('2026-12-25');
        $model->insert();

        $rows = $this->queryTable('test_all_types', 'id = ' . $model->id);
        $this->assertSame('2026/12/25', $rows[0]['custom_date_format']);
    }
}
