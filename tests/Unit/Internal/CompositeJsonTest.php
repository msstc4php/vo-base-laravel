<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Internal;

use JsonException;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositeJson::class)]
final class CompositeJsonTest extends TestCase
{
    public function testJsonTextOfACompositeIsDecoded(): void
    {
        self::assertSame(['lat' => 1, 'lng' => 2], CompositeJson::payload(new ValueObjectMapper(), GeoCoordinates::class, '{"lat":1,"lng":2}'));
    }

    public function testAnArrayOfACompositeStaysAsIs(): void
    {
        self::assertSame(['lat' => 1], CompositeJson::payload(new ValueObjectMapper(), GeoCoordinates::class, ['lat' => 1]));
    }

    public function testTextOfAScalarBackedClassIsNotDecoded(): void
    {
        self::assertSame('"a@b.c"', CompositeJson::payload(new ValueObjectMapper(), EmailAddress::class, '"a@b.c"'));
    }

    public function testInvalidJsonIsAnInvalidPayload(): void
    {
        try {
            CompositeJson::payload(new ValueObjectMapper(), GeoCoordinates::class, '{lat: 1}');
            self::fail('No InvalidPayloadException thrown.');
        } catch (InvalidPayloadException $exception) {
            self::assertSame('A MSSTC4PHP\ValueObject\Geo\GeoCoordinates payload must be JSON text, invalid JSON given.', $exception->getMessage());
            self::assertSame(\JSON_ERROR_SYNTAX, $exception->getCode());
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }
}
