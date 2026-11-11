<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Rules;

use BackedEnum;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Rules\ValidValueObject;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\AsDimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Priority;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\UnmappedValueObject;
use MSSTC4PHP\ValueObject\Network\Ipv4Address;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\Percentage;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidValueObject::class)]
final class ValidValueObjectTest extends TestCase
{
    private ArrayLoader $translations;

    private Factory $validation;

    #[Override]
    protected function setUp(): void
    {
        $this->translations = new ArrayLoader();
        $this->validation = new Factory(new Translator($this->translations, 'en'));
    }

    /**
     * @return iterable<string, array{class-string<ValueObject|BackedEnum>, mixed}>
     */
    public static function validInput(): iterable
    {
        yield 'string-backed' => [EmailAddress::class, 'jane@example.com'];
        yield 'int-backed from a form string' => [Port::class, '8080'];
        yield 'int-backed from JSON' => [Port::class, 8080];
        yield 'float-backed from a form string' => [Percentage::class, '12.5'];
        yield 'float-backed from JSON' => [Percentage::class, 12.5];
        yield 'string enum' => [CountryCode::class, 'DE'];
        yield 'int enum from a form string' => [Priority::class, '9'];
        yield 'composite from JSON' => [GeoCoordinates::class, ['lat' => 52.52, 'lng' => 13.405]];
        yield 'composite as JSON text' => [GeoCoordinates::class, '{"lat":52.52,"lng":13.405}'];
        yield 'an instance' => [EmailAddress::class, EmailAddress::from('jane@example.com')];
        yield 'null' => [EmailAddress::class, null];
        yield 'empty string' => [Port::class, ''];
    }

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     */
    #[DataProvider('validInput')]
    public function testValidInputPasses(string $class, mixed $value): void
    {
        self::assertTrue($this->validation->make(['field' => $value], ['field' => [new ValidValueObject($class)]])->passes());
    }

    /**
     * @return iterable<string, array{class-string<ValueObject|BackedEnum>, mixed, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'string-backed' => [EmailAddress::class, 'not-an-email', 'email address'];
        yield 'int-backed out of range' => [Port::class, '70000', 'port'];
        yield 'int-backed not a number' => [Port::class, '80a', 'port'];
        yield 'int-backed with a leading zero' => [Port::class, '080', 'port'];
        yield 'float-backed with a trailing dot' => [Percentage::class, '12.', 'percentage'];
        yield 'float-backed out of range' => [Percentage::class, '1e400', 'percentage'];
        yield 'string enum' => [CountryCode::class, 'XX', 'country code'];
        yield 'int enum' => [Priority::class, '5', 'priority'];
        yield 'composite out of range' => [GeoCoordinates::class, ['lat' => 91, 'lng' => 0], 'geo coordinates'];
        yield 'composite as invalid JSON text' => [GeoCoordinates::class, '52.52,13.405', 'geo coordinates'];
        yield 'composite as JSON text of the wrong type' => [GeoCoordinates::class, '"52.52,13.405"', 'geo coordinates'];
        yield 'composite of the wrong type' => [GeoCoordinates::class, 52.52, 'geo coordinates'];
        yield 'digits after a lowercase letter' => [Ipv4Address::class, '300.1.1.1', 'ipv4 address'];
        yield 'array for a string-backed one' => [EmailAddress::class, ['jane@example.com'], 'email address'];
        yield 'bool for an int-backed one' => [Port::class, true, 'port'];
    }

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     */
    #[DataProvider('invalidInput')]
    public function testInvalidInputFailsWithTheDefaultMessage(string $class, mixed $value, string $type): void
    {
        $validator = $this->validation->make(['field' => $value], ['field' => [new ValidValueObject($class)]]);

        self::assertTrue($validator->fails());
        self::assertSame(['field' => ['The field field is not a valid ' . $type . '.']], $validator->errors()->toArray());
    }

    /**
     * The validator skips a non-implicit rule for some empty input on its own; called
     * directly, the rule must still let it through.
     */
    #[DataProvider('emptyInput')]
    public function testEmptyInputPassesWhenTheRuleIsCalledDirectly(mixed $value): void
    {
        $failures = [];
        new ValidValueObject(Port::class)->validate('port', $value, static function (string $message) use (&$failures): PotentiallyTranslatedString {
            $failures[] = $message;

            return new PotentiallyTranslatedString($message, new Translator(new ArrayLoader(), 'en'));
        });

        self::assertSame([], $failures);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptyInput(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    public function testRequiredStillRejectsEmptyInput(): void
    {
        $validator = $this->validation->make(['email' => ''], ['email' => ['required', new ValidValueObject(EmailAddress::class)]]);

        self::assertSame(['email' => ['validation.required']], $validator->errors()->toArray());
    }

    public function testCustomMessage(): void
    {
        $validator = $this->validation->make(
            ['email' => 'nope'],
            ['email' => [new ValidValueObject(EmailAddress::class, 'Give :attribute a real :type.')]],
        );

        self::assertSame(['email' => ['Give email a real email address.']], $validator->errors()->toArray());
    }

    public function testMessageIsTranslatedThroughJsonTranslations(): void
    {
        $this->translations->addMessages('de', '*', [ValidValueObject::DEFAULT_MESSAGE => ':attribute ist kein gültiger Wert für :type.'], '*');
        $validator = $this->validation->make(['email' => 'nope'], ['email' => [new ValidValueObject(EmailAddress::class)]]);
        $validator->getTranslator()->setLocale('de');

        self::assertSame(['email' => ['email ist kein gültiger Wert für email address.']], $validator->errors()->toArray());
    }

    public function testCustomMapperCodecIsUsed(): void
    {
        $rule = new ValidValueObject(Dimensions::class, mapper: AsDimensions::mapper());

        self::assertTrue($this->validation->make(['size' => ['width' => 640, 'height' => 480]], ['size' => [$rule]])->passes());
        self::assertSame(
            ['size' => ['The size field is not a valid dimensions.']],
            $this->validation->make(['size' => ['width' => 0, 'height' => 480]], ['size' => [$rule]])->errors()->toArray(),
        );
    }

    public function testBoundedStringIsRefusedBecauseTheInputWouldChooseItsBounds(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('validate the length with the min/max rules or use NonEmptyString');

        new ValidValueObject(BoundedString::class);
    }

    public function testUnsupportedClassIsALogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ValidValueObject::class . ' cannot validate ' . UnmappedValueObject::class);

        new ValidValueObject(UnmappedValueObject::class);
    }
}
