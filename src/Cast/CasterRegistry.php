<?php

declare(strict_types=1);

namespace yuandian\Database\Cast;

use BackedEnum;
use yuandian\Database\Attribute\Cast;

class CasterRegistry
{
    private static array $map = [
        'int'      => IntegerCaster::class,
        'integer'  => IntegerCaster::class,
        'float'    => FloatCaster::class,
        'double'   => FloatCaster::class,
        'string'   => StringCaster::class,
        'str'      => StringCaster::class,
        'bool'     => BooleanCaster::class,
        'boolean'  => BooleanCaster::class,
        'datetime' => DateTimeCaster::class,
        'date'     => DateTimeCaster::class,
        'json'     => JsonCaster::class,
        'array'    => ArrayCaster::class,
    ];

    /** @var array<class-string, class-string<Caster>> */
    private static array $classMap = [
        \DateTimeImmutable::class => DateTimeCaster::class,
        \DateTime::class          => DateTimeCaster::class,
    ];

    private static array $instances = [];

    public static function register(string $type, Caster|string $caster): void
    {
        self::$map[$type] = $caster;
        unset(self::$instances[$type]);
    }

    /**
     * @param Cast|string $typeOrAttribute
     */
    public static function resolve(Cast|string $typeOrAttribute): Caster
    {
        if ($typeOrAttribute instanceof Cast) {
            return self::resolveFromAttribute($typeOrAttribute);
        }

        if (isset(self::$instances[$typeOrAttribute])) {
            return self::$instances[$typeOrAttribute];
        }

        $caster = self::createCaster($typeOrAttribute);
        self::$instances[$typeOrAttribute] = $caster;

        return $caster;
    }

    private static function resolveFromAttribute(Cast $attr): Caster
    {
        $casterClass = $attr->caster;

        if (is_a($casterClass, Caster::class, true)) {
            return match (true) {
                $casterClass === DateTimeCaster::class
                    => new DateTimeCaster($attr->format ?? 'Y-m-d H:i:s'),
                $casterClass === JsonCaster::class
                    => new JsonCaster($attr->castTo),
                $casterClass === ArrayCaster::class
                    => new ArrayCaster(
                        $attr->elements !== null ? self::resolve($attr->elements) : null
                    ),
                default => new $casterClass(),
            };
        }

        return self::resolve($casterClass);
    }

    private static function createCaster(string $type): Caster
    {
        $casterClass = self::$map[$type] ?? null;

        if ($casterClass !== null && !str_contains($type, ':')) {
            return new $casterClass();
        }

        if (str_ends_with($type, '[]')) {
            $elementType = substr($type, 0, -2);
            $elementCaster = self::resolve($elementType);

            return new ArrayCaster($elementCaster);
        }

        if (str_contains($type, ':')) {
            [$baseType, $param] = explode(':', $type, 2);

            $casterClass = self::$map[$baseType] ?? null;

            if ($casterClass !== null && is_a($casterClass, DateTimeCaster::class, true)) {
                return DateTimeCaster::fromParam($param);
            }

            if ($casterClass !== null) {
                return new $casterClass();
            }

            if (class_exists($baseType) && is_subclass_of($baseType, BackedEnum::class)) {
                return new EnumCaster($baseType);
            }

            if (class_exists($type)) {
                return new ObjectCaster($type);
            }

            return new ObjectCaster($baseType);
        }

        if (class_exists($type)) {
            if (is_subclass_of($type, BackedEnum::class)) {
                return new EnumCaster($type);
            }

            if (is_a($type, Caster::class, true)) {
                return new $type();
            }

            $mappedCaster = self::$classMap[$type] ?? null;
            if ($mappedCaster !== null) {
                return new $mappedCaster();
            }

            return new ObjectCaster($type);
        }

        return new StringCaster();
    }
}
