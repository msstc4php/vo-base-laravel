<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Casts;

use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use MSSTC4PHP\ValueObject\Laravel\Casts\CasterCache;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\DimensionsCodec;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CasterCache::class)]
#[UsesClass(AsValueObject::class)]
final class CasterCacheTest extends TestCase
{
    public function testACasterIsKeptPerClassAndMapper(): void
    {
        $mapper = new ValueObjectMapper([new DimensionsCodec()]);
        $first = CasterCache::of(Dimensions::class, $mapper);

        self::assertSame($first, CasterCache::of(Dimensions::class, $mapper));
        self::assertNotSame($first, CasterCache::of(Dimensions::class, new ValueObjectMapper([new DimensionsCodec()])));
    }
}
