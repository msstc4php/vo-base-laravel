<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use Override;

/**
 * A user-defined composite: the core has no codec for it, {@see DimensionsCodec} adds one.
 */
final readonly class Dimensions implements ValueObject
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidValueObjectException('Dimensions must be positive.');
        }
    }

    #[Override]
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $other->width === $this->width && $other->height === $this->height;
    }

    /**
     * @return array{width: int, height: int}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['width' => $this->width, 'height' => $this->height];
    }
}
