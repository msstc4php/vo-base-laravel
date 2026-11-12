<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Internal;

use JsonException;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;

/**
 * A composite also arrives as JSON text: a SQL column, a form field, a query string.
 *
 * @internal shared by the cast, the rule and the request input
 */
final class CompositeJson
{
    private function __construct()
    {
    }

    /**
     * @template TValue the input, returned unchanged unless it is the JSON text of a composite
     *
     * @param class-string $class
     * @param TValue $value
     *
     * @return TValue|array<array-key, mixed> the decoded JSON object for a composite given as a string, $value otherwise
     *
     * @throws InvalidPayloadException when the text is not valid JSON
     */
    public static function payload(ValueObjectMapper $mapper, string $class, mixed $value): mixed
    {
        if (!\is_string($value) || !$mapper->isComposite($class)) {
            return $value;
        }

        try {
            $decoded = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPayloadException(\sprintf('A %s payload must be JSON text, invalid JSON given.', $class), $exception->getCode(), previous: $exception);
        }

        // JSON text that is no object stays the string it was, for the mapper's "string given".
        return \is_array($decoded) ? $decoded : $value;
    }
}
