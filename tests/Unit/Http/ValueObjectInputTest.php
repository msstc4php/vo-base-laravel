<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Http;

use Countable;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\ValidationException;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Http\RequestMacros;
use MSSTC4PHP\ValueObject\Laravel\Http\ValueObjectInput;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Laravel\Internal\FieldName;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Laravel\Tests\ApplicationTestCase;
use MSSTC4PHP\ValueObject\Laravel\ValueObjectServiceProvider;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ValueObjectInput::class)]
#[UsesClass(CompositeJson::class)]
#[UsesClass(FieldName::class)]
#[UsesClass(TypeLabel::class)]
#[UsesClass(RequestMacros::class)]
#[UsesClass(ValueObjectServiceProvider::class)]
/**
 * ValidationException::withMessages() needs the application (Validator facade).
 */
final class ValueObjectInputTest extends ApplicationTestCase
{
    /**
     * A translation group named like the message would make Translator::get() return an array.
     */
    public function testUntranslatableMessageFallsBackToTheDefaultWithItsPlaceholdersReplaced(): void
    {
        $translator = new class implements Translator {
            /**
             * @param array<array-key, mixed> $replace
             *
             * @return array{nested: string}
             */
            #[Override]
            public function get($key, array $replace = [], $locale = null): array
            {
                return ['nested' => $key];
            }

            /**
             * @param Countable|int|float|array<array-key, mixed> $number
             * @param array<array-key, mixed> $replace
             */
            #[Override]
            public function choice($key, $number, array $replace = [], $locale = null): string
            {
                return $key;
            }

            #[Override]
            public function getLocale(): string
            {
                return 'en';
            }

            #[Override]
            public function setLocale($locale): void
            {
            }
        };

        try {
            new ValueObjectInput(new ValueObjectMapper(), $translator)->convert('user_email', 'nope', EmailAddress::class);
            self::fail('No ValidationException thrown.');
        } catch (ValidationException $exception) {
            self::assertSame(['user_email' => ['The user email field is not a valid email address.']], $exception->errors());
        }
    }
}
