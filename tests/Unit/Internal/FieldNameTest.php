<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Internal;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use MSSTC4PHP\ValueObject\Laravel\Internal\FieldName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldName::class)]
final class FieldNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'snake_case' => ['user_email', 'user email'];
        yield 'camelCase' => ['userEmail', 'user email'];
        yield 'one word' => ['email', 'email'];
    }

    #[DataProvider('names')]
    public function testKeyReadsLikeLaravelsDisplayableAttribute(string $key, string $name): void
    {
        self::assertSame($name, FieldName::of($key));
    }

    public function testTranslatedAttributeNameWins(): void
    {
        self::assertSame('e-mail address', FieldName::of('user_email', $this->translator()));
        self::assertSame('port', FieldName::of('port', $this->translator()));
    }

    public function testCustomAttributeNameWinsOverTheTranslation(): void
    {
        self::assertSame('contact', FieldName::of('user_email', $this->translator(), ['user_email' => 'contact']));
    }

    private function translator(): Translator
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'validation', ['attributes' => ['user_email' => 'e-mail address']]);

        return new Translator($loader, 'en');
    }
}
