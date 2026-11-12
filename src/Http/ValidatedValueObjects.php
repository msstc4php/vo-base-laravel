<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * For a FormRequest: `$request->valueObject('email', EmailAddress::class)` reads `validated()`, so only fields the
 * rules cover are read, after the ValidValueObject rule vetted them. Replaces the request macro of the same name on
 * the requests that use it. Laravel's `safe()` (ValidatedInput) is not macroable, so there is no `safe()` form.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatedValueObjects
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null null for a field that is absent, null, '' or not in `rules()`
     *
     * @throws ValidationException when the value is not a valid T (a rule other than ValidValueObject let it through)
     * @throws LogicException when the mapper does not support T
     */
    public function valueObject(string $key, string $class): ?object
    {
        return ValueObjectInput::from($this->container)->convert($key, $this->validated($key), $class, $this->attributes());
    }
}
