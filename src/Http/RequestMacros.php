<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Http;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use LogicException;

/**
 * `$request->valueObject('email', EmailAddress::class)`: the input as a value object, null when not filled,
 * a ValidationException (422, the field's error) when invalid. Unlike `$request->enum()` there is no silent
 * fallback, which would hide a client error.
 */
final class RequestMacros
{
    public static function register(): void
    {
        Request::macro('valueObject', function (string $key, string $class): ?object {
            if (!class_exists($class)) {
                throw new LogicException(\sprintf('"%s" is not a class, so input "%s" cannot become one.', $class, $key));
            }

            return ValueObjectInput::from(Container::getInstance())->convert($key, $this->input($key), $class);
        });
    }
}
