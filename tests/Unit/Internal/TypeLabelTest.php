<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Internal;

use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Priority;
use MSSTC4PHP\ValueObject\Network\Ipv4Address;
use MSSTC4PHP\ValueObject\Network\Port;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(TypeLabel::class)]
final class TypeLabelTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function labels(): iterable
    {
        yield 'two words' => [EmailAddress::class, 'email address'];
        yield 'a digit before a capital' => [Ipv4Address::class, 'ipv4 address'];
        yield 'one word' => [Port::class, 'port'];
        yield 'an enum' => [Priority::class, 'priority'];
        yield 'a composite' => [GeoCoordinates::class, 'geo coordinates'];
        yield 'a class without a namespace' => [stdClass::class, 'std class'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('labels')]
    public function testLabelIsTheShortNameAsLowercaseWords(string $class, string $label): void
    {
        self::assertSame($label, TypeLabel::of($class));
    }
}
