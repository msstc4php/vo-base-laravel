<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Internal;

/**
 * The words a client sees instead of a class name: `EmailAddress` becomes "email address".
 *
 * @internal
 */
final class TypeLabel
{
    /**
     * @param class-string $class
     */
    public static function of(string $class): string
    {
        $separator = strrpos($class, '\\');
        $short = $separator === false ? $class : substr($class, $separator + 1);

        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $short));
    }
}
