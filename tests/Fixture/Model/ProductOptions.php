<?php
declare(strict_types=1);

namespace yuandian\Database\Tests\Fixture\Model;

class ProductOptions implements \JsonSerializable
{
    public function __construct(
        public string $color = '',
        public int $size = 0,
    ) {}

    public function jsonSerialize(): array
    {
        return ['color' => $this->color, 'size' => $this->size];
    }
}
