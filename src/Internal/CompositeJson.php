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
    /**
     * @param class-string $class
     *
     * @return mixed the decoded JSON for a composite given as a string, $value otherwise
     *
     * @throws InvalidPayloadException when the text is not valid JSON
     */
    public static function payload(ValueObjectMapper $mapper, string $class, mixed $value): mixed
    {
        if (!\is_string($value) || !$mapper->isComposite($class)) {
            return $value;
        }

        try {
            return json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPayloadException(\sprintf('A %s payload must be JSON text, invalid JSON given.', $class), $exception->getCode(), previous: $exception);
        }
    }
}
