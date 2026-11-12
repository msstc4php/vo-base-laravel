<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Translation\Translator;
use Illuminate\Validation\ValidationException;
use LogicException;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Http\RequestMacros;
use MSSTC4PHP\ValueObject\Laravel\Http\ValueObjectInput;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Laravel\Tests\ApplicationTestCase;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\DimensionsCodec;
use MSSTC4PHP\ValueObject\Laravel\ValueObjectServiceProvider;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use stdClass;

#[CoversClass(RequestMacros::class)]
#[CoversClass(ValueObjectInput::class)]
#[UsesClass(ValueObjectServiceProvider::class)]
#[UsesClass(TypeLabel::class)]
final class RequestValueObjectTest extends ApplicationTestCase
{
    public function testTheProviderRegistersTheMacro(): void
    {
        self::assertTrue(Request::hasMacro('valueObject'));
    }

    public function testValidInputBecomesTheValueObject(): void
    {
        $email = $this->valueObject(Request::create('/', 'GET', ['email' => 'jane@example.com']), 'email', EmailAddress::class);

        self::assertEquals(EmailAddress::from('jane@example.com'), $email);
    }

    public function testFormStringBecomesAnIntBackedValueObject(): void
    {
        self::assertEquals(Port::from(8080), $this->valueObject(Request::create('/', 'GET', ['port' => '8080']), 'port', Port::class));
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function notFilled(): iterable
    {
        yield 'missing' => [[]];
        yield 'empty string' => [['email' => '']];
        yield 'null' => [['email' => null]];
    }

    /**
     * @param array<string, string|null> $query
     */
    #[DataProvider('notFilled')]
    public function testInputThatIsNotFilledIsNull(array $query): void
    {
        self::assertNull($this->valueObject(Request::create('/', 'GET', $query), 'email', EmailAddress::class));
    }

    public function testAnInstanceAlreadyInTheInputPassesThrough(): void
    {
        $email = EmailAddress::from('jane@example.com');
        $request = Request::create('/');
        $request->merge(['email' => $email]);

        self::assertSame($email, $this->valueObject($request, 'email', EmailAddress::class));
    }

    public function testInvalidInputIsAFieldError(): void
    {
        $exception = $this->validationFailure(Request::create('/', 'GET', ['email' => 'nope']), 'email', EmailAddress::class);

        self::assertSame(['email' => ['The email field is not a valid email address.']], $exception->errors());
        self::assertSame(422, $exception->status);
    }

    public function testArrayForAScalarIsAFieldError(): void
    {
        $exception = $this->validationFailure($this->jsonRequest(['email' => ['jane@example.com']]), 'email', EmailAddress::class);

        self::assertSame(['email' => ['The email field is not a valid email address.']], $exception->errors());
    }

    public function testUnderscoresInTheKeyBecomeSpaces(): void
    {
        $exception = $this->validationFailure(Request::create('/', 'GET', ['user_email' => 'nope']), 'user_email', EmailAddress::class);

        self::assertSame(['user_email' => ['The user email field is not a valid email address.']], $exception->errors());
    }

    public function testMessageIsTranslated(): void
    {
        $this->application()->make(Translator::class)->addJsonPath(\dirname(__DIR__, 2) . '/Fixtures/lang');
        $this->application()->setLocale('de');

        $exception = $this->validationFailure(Request::create('/', 'GET', ['email' => 'nope']), 'email', EmailAddress::class);

        self::assertSame(['email' => ['email ist keine gültige email address.']], $exception->errors());
    }

    public function testCompositeFromAJsonObject(): void
    {
        $location = $this->valueObject($this->jsonRequest(['location' => ['lat' => 52.52, 'lng' => 13.405]]), 'location', GeoCoordinates::class);

        self::assertEquals(GeoCoordinates::fromFloats(52.52, 13.405), $location);
    }

    public function testCompositeFromJsonText(): void
    {
        $location = $this->valueObject(Request::create('/', 'GET', ['location' => '{"lat":52.52,"lng":13.405}']), 'location', GeoCoordinates::class);

        self::assertEquals(GeoCoordinates::fromFloats(52.52, 13.405), $location);
    }

    public function testCompositeFromInvalidJsonTextIsAFieldError(): void
    {
        $exception = $this->validationFailure(Request::create('/', 'GET', ['location' => '52.52,13.405']), 'location', GeoCoordinates::class);

        self::assertSame(['location' => ['The location field is not a valid geo coordinates.']], $exception->errors());
    }

    public function testTrustedSourceOnlyClassIsAFieldError(): void
    {
        $exception = $this->validationFailure($this->jsonRequest(['name' => ['value' => 'x', 'minLength' => 0, 'maxLength' => 1]]), 'name', BoundedString::class);

        self::assertSame(['name' => ['The name field is not a valid bounded string.']], $exception->errors());
    }

    public function testUnsupportedClassIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);

        $this->valueObject(Request::create('/'), 'email', stdClass::class);
    }

    public function testAClassNameThatIsNoClassIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"NoSuchClass" is not a class');

        $request = Request::create('/');
        $request->__call('valueObject', ['email', 'NoSuchClass']);
    }

    public function testTheMapperComesFromTheContainer(): void
    {
        $this->application()->singleton(ValueObjectMapper::class, static fn (): ValueObjectMapper => new ValueObjectMapper([new DimensionsCodec()]));

        $dimensions = $this->valueObject($this->jsonRequest(['size' => ['width' => 2, 'height' => 3]]), 'size', Dimensions::class);

        self::assertEquals(new Dimensions(2, 3), $dimensions);
    }

    /**
     * @param class-string $class
     */
    private function validationFailure(Request $request, string $key, string $class): ValidationException
    {
        try {
            $this->valueObject($request, $key, $class);
        } catch (ValidationException $exception) {
            self::assertStringNotContainsString('MSSTC4PHP', $exception->getMessage());
            self::assertStringNotContainsString('MSSTC4PHP', (string) json_encode($exception->errors()));

            return $exception;
        }

        self::fail('No ValidationException thrown.');
    }

    /**
     * The macro is invisible to PHPStan without Larastan, hence the explicit __call().
     *
     * @param class-string $class
     */
    private function valueObject(Request $request, string $key, string $class): mixed
    {
        return $request->__call('valueObject', [$key, $class]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): Request
    {
        return Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode($body));
    }
}
