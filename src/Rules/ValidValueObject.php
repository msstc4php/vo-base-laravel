<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Rules;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;

/**
 * Passes when the input is a valid payload of the value object, or an instance of
 * it: the value object is the single source of validation truth. Null and '' pass;
 * emptiness is the job of `required`. A wrong input type fails like invalid content.
 */
final readonly class ValidValueObject implements ValidationRule
{
    /**
     * Also the translation key: a `lang/<locale>.json` entry with this text translates it.
     */
    public const string DEFAULT_MESSAGE = 'The :attribute field is not a valid :type.';

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     * @param non-empty-string|null $message replaces DEFAULT_MESSAGE; `:attribute` and `:type` (the class's short name as lowercase words, "email address") are replaced
     *
     * @throws LogicException when $class is not supported, or is trusted-source only (BoundedString)
     */
    public function __construct(
        private string $class,
        private ?string $message = null,
        private ValueObjectMapper $mapper = new ValueObjectMapper(),
    ) {
        if (!$mapper->supports($class)) {
            throw new LogicException(\sprintf('%s cannot validate %s: it is neither a scalar-backed value object, a backed enum nor a class with a payload codec.', self::class, $class));
        }

        // Its payload carries its own constraints (BoundedString's bounds), so request input would choose them.
        if (!$mapper->supportsUntrusted($class)) {
            throw new LogicException(\sprintf('%s does not validate %s from request input: its payload carries its own constraints. For BoundedString, validate the length with the min/max rules or use NonEmptyString.', self::class, $class));
        }
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value instanceof $this->class) {
            return;
        }

        try {
            $this->mapper->fromUntrustedPayload($this->class, CompositeJson::payload($this->mapper, $this->class, $value));
        } catch (InvalidValueObjectException) {
            $fail($this->message ?? self::DEFAULT_MESSAGE)->translate(['type' => TypeLabel::of($this->class)]);
        }
    }
}
