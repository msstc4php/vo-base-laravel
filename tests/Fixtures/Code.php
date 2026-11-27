<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use MSSTC4PHP\ValueObject\Contract\ResolvesConcreteClass;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Support\AbstractStringValueObject;
use Override;

/**
 * Up to three letters is a {@see ShortCode}, more a {@see LongCode}: an abstract type the mapper builds through from().
 */
abstract class Code extends AbstractStringValueObject implements ResolvesConcreteClass
{
    #[Override]
    public static function from(string $value): static
    {
        $code = self::detect($value);

        return $code instanceof static ? $code : throw new InvalidValueObjectException('The code is of another kind.');
    }

    #[Override]
    protected function parse(string $value): string
    {
        if (preg_match($this->pattern(), $value) !== 1) {
            throw new InvalidValueObjectException('Not a valid code.');
        }

        return $value;
    }

    /**
     * @return non-empty-string
     */
    abstract protected function pattern(): string;

    private static function detect(string $value): self
    {
        return \strlen($value) <= 3 ? new ShortCode($value) : new LongCode($value);
    }
}
