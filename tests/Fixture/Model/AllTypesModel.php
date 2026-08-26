<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\Cast;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Cast\DateTimeCaster;
use yuandian\Database\Cast\JsonCaster;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_all_types')]
class AllTypesModel extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public int $intField = 0;
    public float $floatField = 0.0;
    public string $stringField = '';
    public bool $boolField = false;

    public ?int $nullableInt = null;
    public ?float $nullableFloat = null;
    public ?string $nullableString = null;
    public ?bool $nullableBool = null;

    public ?\DateTimeImmutable $dateTimeField = null;

    #[Cast(JsonCaster::class)]
    public ?array $jsonArray = null;

    #[Cast(JsonCaster::class, castTo: ProductOptions::class)]
    public ?ProductOptions $jsonObject = null;

    #[Cast(DateTimeCaster::class, format: 'Y/m/d')]
    public ?\DateTimeImmutable $customDateFormat = null;
}
