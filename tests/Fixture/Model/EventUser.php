<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

use yuandian\Database\Attribute\SoftDelete;
use yuandian\Database\Attribute\Table;
use yuandian\Database\Attribute\TableId;
use yuandian\Database\Enums\IdType;
use yuandian\Database\Model\Model;

#[Table('test_event_user')]
#[SoftDelete]
class EventUser extends Model
{
    #[TableId(IdType::AUTO)]
    public int $id = 0;

    public string $name = '';

    public string $slug = '';

    public int $blocked = 0;

    public function onBeforeInsert(): bool
    {
        if ($this->name !== '') {
            $this->slug = strtolower(str_replace(' ', '-', $this->name));
        }
        return true;
    }

    public function onBeforeDelete(): bool
    {
        if ($this->blocked) {
            return false;
        }
        return true;
    }
}
