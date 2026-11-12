<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Internal;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Str;

/**
 * An input key as a client reads it, resolved like Laravel's validator displays attributes: a custom attribute
 * name, then a `validation.attributes.<key>` translation, then the key in words (`userEmail` → "user email").
 *
 * @internal
 */
final class FieldName
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $custom a form request's `attributes()`
     */
    public static function of(string $key, ?Translator $translator = null, array $custom = []): string
    {
        if (\is_string($custom[$key] ?? null)) {
            return $custom[$key];
        }

        $line = $translator?->get('validation.attributes.' . $key);
        if (\is_string($line) && $line !== 'validation.attributes.' . $key) {
            return $line;
        }

        return str_replace('_', ' ', Str::snake($key));
    }
}
