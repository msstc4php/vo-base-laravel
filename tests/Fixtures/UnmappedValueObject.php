<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use MSSTC4PHP\ValueObject\Contract\ValueObject;
use Override;

/**
 * A composite without a payload codec: the mapper does not support it.
 */
final readonly class UnmappedValueObject implements ValueObject
{
    #[Override]
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self;
    }

    /**
     * @return array<never, never>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [];
    }
}
