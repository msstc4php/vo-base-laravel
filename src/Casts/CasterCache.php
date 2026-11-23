<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Casts;

use BackedEnum;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use WeakMap;

/**
 * The casters Eloquent asks for on every attribute access, one per class and mapper: a mapper rebound in the
 * container gets casters of its own, and they go with it.
 *
 * @internal
 */
final class CasterCache
{
    /** @var WeakMap<ValueObjectMapper, array<class-string<ValueObject|BackedEnum>, AsValueObject<ValueObject|BackedEnum>>>|null */
    private static ?WeakMap $byMapper = null;

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     *
     * @return AsValueObject<ValueObject|BackedEnum>
     */
    public static function of(string $class, ValueObjectMapper $mapper): AsValueObject
    {
        self::$byMapper ??= new WeakMap();
        $byClass = self::$byMapper[$mapper] ?? [];
        $caster = $byClass[$class] ??= new AsValueObject($class, $mapper);
        self::$byMapper[$mapper] = $byClass;

        return $caster;
    }
}
