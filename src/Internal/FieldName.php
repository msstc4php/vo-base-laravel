<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Internal;

/**
 * An input key as a client reads it, as Laravel's validator displays attributes: `user_email` becomes "user email".
 *
 * @internal
 */
final class FieldName
{
    public static function of(string $key): string
    {
        return str_replace('_', ' ', $key);
    }
}
