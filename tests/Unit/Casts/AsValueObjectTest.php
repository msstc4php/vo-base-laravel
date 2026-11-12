<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Casts;

use LogicException;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Customer;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\MisconfiguredCustomer;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\UnmappedValueObject;
use MSSTC4PHP\ValueObject\Network\Port;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

#[CoversClass(AsValueObject::class)]
#[UsesClass(CompositeJson::class)]
final class AsValueObjectTest extends TestCase
{
    public function testOfBuildsTheStringDefinition(): void
    {
        self::assertSame(AsValueObject::class . ':' . EmailAddress::class, AsValueObject::of(EmailAddress::class));
    }

    public function testCastUsingBuildsTheCaster(): void
    {
        $caster = AsValueObject::castUsing([Port::class]);

        self::assertSame(80, $caster->set(new Customer(), 'port', Port::from(80), []));
    }

    public function testCastUsingReusesTheCasterPerClass(): void
    {
        self::assertSame(AsValueObject::castUsing([Port::class]), AsValueObject::castUsing([Port::class]));
        self::assertNotSame(AsValueObject::castUsing([Port::class]), AsValueObject::castUsing([EmailAddress::class]));
    }

    public function testCastersShareTheDefaultMapper(): void
    {
        $mapper = new ReflectionProperty(AsValueObject::class, 'mapper');

        self::assertSame($mapper->getValue(new AsValueObject(Port::class)), $mapper->getValue(new AsValueObject(EmailAddress::class)));
    }

    /**
     * @return iterable<string, array{array<array-key, string>, string}>
     */
    public static function invalidDefinitions(): iterable
    {
        $malformed = 'expects exactly one value object class';
        yield 'no class' => [[], $malformed];
        yield 'empty class' => [[''], $malformed];
        yield 'two classes' => [[EmailAddress::class, Port::class], $malformed];
        yield 'not a value object' => [[stdClass::class], $malformed];
        yield 'unknown class' => [['App\\Missing'], $malformed];
        yield 'value object without codec' => [[UnmappedValueObject::class], 'cannot cast to ' . UnmappedValueObject::class];
    }

    /**
     * @param array<array-key, string> $arguments
     */
    #[DataProvider('invalidDefinitions')]
    public function testInvalidDefinitionIsALogicException(array $arguments, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(AsValueObject::class . ' ' . $message);

        AsValueObject::castUsing($arguments);
    }

    public function testUnsupportedClassIsALogicExceptionWhenTheModelUsesTheCast(): void
    {
        $this->expectException(LogicException::class);

        new MisconfiguredCustomer()->setAttribute('email', 'jane@example.com');
    }

    public function testCasterWithoutMapperSupportIsALogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(AsValueObject::class . ' cannot cast to ' . UnmappedValueObject::class);

        new AsValueObject(UnmappedValueObject::class);
    }
}
