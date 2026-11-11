<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Rules;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use Override;

/**
 * Passes when the input is a valid payload of the value object: the value object
 * is the single source of validation truth. Null and '' pass; emptiness is the
 * job of `required`. A wrong input type fails like invalid content.
 */
final readonly class ValidValueObject implements ValidationRule
{
    /**
     * Also the translation key: a `lang/<locale>.json` entry with this text translates it.
     */
    public const string DEFAULT_MESSAGE = 'The :attribute field is not a valid :type.';

    /**
     * @param class-string<ValueObject|BackedEnum> $class
     * @param non-empty-string|null $message replaces DEFAULT_MESSAGE; `:attribute` and `:type` (the class's short name) are replaced
     *
     * @throws LogicException when $class is not supported, or is BoundedString
     */
    public function __construct(
        private string $class,
        private ?string $message = null,
        private ValueObjectMapper $mapper = new ValueObjectMapper(),
    ) {
        if (!$mapper->supports($class)) {
            throw new LogicException(\sprintf('%s cannot validate %s: it is neither a scalar-backed value object, a backed enum nor a class with a payload codec.', self::class, $class));
        }

        // Its payload carries its own bounds, so request input would choose the limits it is checked against.
        if (is_a($class, BoundedString::class, true)) {
            throw new LogicException(\sprintf('%s does not validate %s from request input: validate the length with the min/max rules or use NonEmptyString.', self::class, BoundedString::class));
        }
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            $this->mapper->fromPayload($this->class, $value);
        } catch (InvalidValueObjectException) {
            $fail($this->message ?? self::DEFAULT_MESSAGE)->translate(['type' => $this->shortClassName()]);
        }
    }

    private function shortClassName(): string
    {
        $separator = strrpos($this->class, '\\');

        return $separator === false ? $this->class : substr($this->class, $separator + 1);
    }
}
