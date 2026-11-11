<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Payload\PayloadCodec;
use Override;

/**
 * @implements PayloadCodec<Dimensions>
 */
final readonly class DimensionsCodec implements PayloadCodec
{
    #[Override]
    public function valueObjectClass(): string
    {
        return Dimensions::class;
    }

    /**
     * @return array{width: int, height: int}
     */
    #[Override]
    public function encode(ValueObject $value): array
    {
        if (!$value instanceof Dimensions) {
            throw new LogicException(\sprintf('%s encodes %s only.', self::class, Dimensions::class));
        }

        return $value->jsonSerialize();
    }

    #[Override]
    public function decode(array $payload): Dimensions
    {
        $width = $payload['width'] ?? null;
        $height = $payload['height'] ?? null;
        if (!\is_int($width) || !\is_int($height)) {
            throw new InvalidPayloadException('Expected integer "width" and "height".');
        }

        return new Dimensions($width, $height);
    }
}
