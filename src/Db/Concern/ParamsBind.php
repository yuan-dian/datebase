<?php

declare(strict_types=1);

namespace yuandian\Database\Db\Concern;

trait ParamsBind
{
    public function bind(string $key, mixed $value, int $type = 2): static
    {
        $this->bind[$key] = [$value, $type];
        return $this;
    }

    public function getBind(): array
    {
        return $this->bind;
    }

    public function clearBind(): static
    {
        $this->bind = [];
        return $this;
    }
}
