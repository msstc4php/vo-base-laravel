<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Internal;

use MSSTC4PHP\ValueObject\Laravel\Internal\FieldName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldName::class)]
final class FieldNameTest extends TestCase
{
    public function testUnderscoresBecomeSpaces(): void
    {
        self::assertSame('user email', FieldName::of('user_email'));
        self::assertSame('email', FieldName::of('email'));
    }
}
