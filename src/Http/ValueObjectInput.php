<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\ValidationException;
use LogicException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Laravel\Internal\FieldName;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Laravel\Rules\ValidValueObject;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;

/**
 * One input value to a value object, with the rule's message as the field error.
 *
 * @internal shared by the request macro and the form request trait
 */
final readonly class ValueObjectInput
{
    public function __construct(
        private ValueObjectMapper $mapper,
        private Translator $translator,
    ) {
    }

    public static function from(Container $container): self
    {
        return new self($container->make(ValueObjectMapper::class), $container->make(Translator::class));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null null for a missing, null or '' value
     *
     * @throws ValidationException when the value is not a valid T, or T is trusted-source only
     * @throws LogicException when the mapper does not support T
     */
    public function convert(string $key, mixed $value, string $class): ?object
    {
        if (!$this->mapper->supports($class)) {
            throw new LogicException(\sprintf('%s is neither a scalar-backed value object, a backed enum nor a class with a payload codec, so input "%s" cannot become one.', $class, $key));
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $class) {
            return $value;
        }

        try {
            return $this->mapper->fromUntrustedPayload($class, CompositeJson::payload($this->mapper, $class, $value));
        } catch (InvalidValueObjectException) {
            throw ValidationException::withMessages([$key => $this->message($key, $class)]);
        }
    }

    /**
     * @param class-string $class
     */
    private function message(string $key, string $class): string
    {
        $replace = ['attribute' => FieldName::of($key), 'type' => TypeLabel::of($class)];
        $message = $this->translator->get(ValidValueObject::DEFAULT_MESSAGE, $replace);

        return \is_string($message)
            ? $message
            : strtr(ValidValueObject::DEFAULT_MESSAGE, [':attribute' => $replace['attribute'], ':type' => $replace['type']]);
    }
}
