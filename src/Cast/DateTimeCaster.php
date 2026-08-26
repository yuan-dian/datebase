<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

use DateTimeImmutable;
use DateTimeInterface;

final class DateTimeCaster implements Caster
{
    private readonly string $format;

    public function __construct(string $format = 'Y-m-d H:i:s')
    {
        $this->format = $format;
    }

    public static function fromParam(string $format): static
    {
        return new static($format);
    }

    public function fromDb(mixed $value): DateTimeImmutable|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        $date = DateTimeImmutable::createFromFormat($this->format, (string) $value);
        if ($date === false) {
            $date = new DateTimeImmutable((string) $value);
        }

        return $date;
    }

    public function toDb(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($this->format);
        }

        return (string) $value;
    }
}
