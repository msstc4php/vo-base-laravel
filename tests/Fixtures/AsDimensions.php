<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use Illuminate\Contracts\Database\Eloquent\Castable;
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;

/**
 * The README's recipe for a custom codec with a string cast definition.
 */
final readonly class AsDimensions implements Castable
{
    public static function mapper(): ValueObjectMapper
    {
        return new ValueObjectMapper([new DimensionsCodec()]);
    }

    /**
     * @param array<array-key, string> $arguments
     *
     * @return AsValueObject<Dimensions>
     */
    #[Override]
    public static function castUsing(array $arguments): AsValueObject
    {
        return new AsValueObject(Dimensions::class, self::mapper());
    }
}
