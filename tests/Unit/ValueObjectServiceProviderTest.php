<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit;

use MSSTC4PHP\ValueObject\Laravel\Tests\ApplicationTestCase;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\DimensionsCodec;
use MSSTC4PHP\ValueObject\Laravel\ValueObjectServiceProvider;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValueObjectServiceProvider::class)]
final class ValueObjectServiceProviderTest extends ApplicationTestCase
{
    public function testMapperIsASingleton(): void
    {
        $app = $this->application();

        self::assertSame($app->make(ValueObjectMapper::class), $app->make(ValueObjectMapper::class));
        self::assertFalse($app->make(ValueObjectMapper::class)->supports(Dimensions::class));
    }

    public function testApplicationMayRebindTheMapperWithCodecs(): void
    {
        $app = $this->application();
        $app->singleton(ValueObjectMapper::class, static fn (): ValueObjectMapper => new ValueObjectMapper([new DimensionsCodec()]));

        self::assertTrue($app->make(ValueObjectMapper::class)->supports(Dimensions::class));
    }

    public function testMapperBoundBeforeTheProviderIsKept(): void
    {
        $app = $this->application();
        $mapper = new ValueObjectMapper([new DimensionsCodec()]);
        $app->instance(ValueObjectMapper::class, $mapper);

        $app->register(ValueObjectServiceProvider::class, force: true);

        self::assertSame($mapper, $app->make(ValueObjectMapper::class));
    }
}
