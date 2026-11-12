<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;
use LogicException;
use MSSTC4PHP\ValueObject\Contract\ValueObject;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;

/**
 * Eloquent cast for every class {@see ValueObjectMapper} supports: a scalar
 * column holds the payload as is, a composite column holds it as JSON text.
 *
 * Assigning a raw payload (`$model->email = 'a@b.c'`) is accepted and validated
 * through the value object, so the attribute never holds an invalid value. A raw
 * payload is treated as untrusted input: a class whose payload carries its own
 * constraints (BoundedString) must be assigned as an instance. Query expressions
 * (`DB::raw()`) are not payloads and are rejected too.
 *
 * @template TValue of ValueObject|BackedEnum the value object class the attribute holds
 *
 * @implements CastsAttributes<TValue, TValue|string|int|float|array<string, scalar|null>>
 */
final readonly class AsValueObject implements Castable, CastsAttributes, ComparesCastableAttributes, SerializesCastableAttributes
{
    private ValueObjectMapper $mapper;

    /**
     * @param class-string<TValue> $class
     * @param ValueObjectMapper|null $mapper null for the default mapper, shared by every cast
     *
     * @throws LogicException when the mapper does not support $class
     */
    public function __construct(
        private string $class,
        ?ValueObjectMapper $mapper = null,
    ) {
        $this->mapper = $mapper ?? self::defaultMapper();
        if (!$this->mapper->supports($class)) {
            throw new LogicException(\sprintf('%s cannot cast to %s: it is neither a scalar-backed value object, a backed enum nor a class with a payload codec.', self::class, $class));
        }
    }

    /**
     * The cast definition for `$casts` / `casts()`: `AsValueObject::of(EmailAddress::class)`.
     *
     * @param class-string<ValueObject|BackedEnum> $class
     *
     * @return non-empty-string
     */
    public static function of(string $class): string
    {
        return self::class . ':' . $class;
    }

    /**
     * Eloquent resolves the caster on every attribute access, so casters are kept per class.
     *
     * @param array<array-key, string> $arguments
     *
     * @return self<ValueObject|BackedEnum>
     *
     * @throws LogicException when the cast definition names no supported class
     */
    #[Override]
    public static function castUsing(array $arguments): self
    {
        /** @var array<class-string<ValueObject|BackedEnum>, self<ValueObject|BackedEnum>> $casters */
        static $casters = [];

        $class = $arguments[0] ?? '';
        if (\count($arguments) !== 1 || (!is_a($class, ValueObject::class, true) && !is_a($class, BackedEnum::class, true))) {
            throw new LogicException(\sprintf('%s expects exactly one value object class, as in %s::of(EmailAddress::class); got "%s".', self::class, self::class, implode(',', $arguments)));
        }

        return $casters[$class] ??= new self($class, self::defaultMapper());
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return TValue|null
     *
     * @throws InvalidValueObjectException when the stored value is not a valid TValue
     */
    #[Override]
    public function get(Model $model, string $key, mixed $value, array $attributes): ?object
    {
        return $value === null ? null : $this->toValueObject($value, trusted: true);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @throws InvalidValueObjectException when $value is neither a TValue nor a valid payload of one;
     *                                     InvalidPayloadException for a raw payload of a trusted-source-only class
     */
    #[Override]
    public function set(Model $model, string $key, mixed $value, array $attributes): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        $payload = $this->mapper->toPayload($this->toValueObject($value, trusted: false));
        if (!\is_array($payload)) {
            return $payload;
        }

        try {
            return json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPayloadException(\sprintf('A %s payload cannot be encoded as JSON.', $this->class), $exception->getCode(), previous: $exception);
        }
    }

    /**
     * The payload form, so `toArray()` and `toJson()` stay plain data and a
     * composite keeps its structure instead of becoming a JSON string inside JSON.
     *
     * @param array<string, mixed> $attributes
     *
     * @return string|int|float|array<string, scalar|null>|null
     */
    #[Override]
    public function serialize(Model $model, string $key, mixed $value, array $attributes): string|int|float|array|null
    {
        return $this->payloadOf($value);
    }

    /**
     * Compares by payload, so a composite whose JSON the database reformatted
     * (spacing, key order of a jsonb column) is not reported as dirty.
     */
    #[Override]
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        try {
            return $this->payloadOf($firstValue) === $this->payloadOf($secondValue);
        } catch (InvalidValueObjectException) {
            // An unreadable stored value differs from anything; overwriting it must stay possible.
            return false;
        }
    }

    /**
     * @return string|int|float|array<string, scalar|null>|null
     */
    private function payloadOf(mixed $value): string|int|float|array|null
    {
        return $value === null ? null : $this->mapper->toPayload($this->toValueObject($value, trusted: true));
    }

    /**
     * @param bool $trusted false for an assigned value, which may come from `fill($request->all())`
     *
     * @return TValue
     */
    private function toValueObject(mixed $value, bool $trusted): object
    {
        if ($value instanceof $this->class) {
            return $value;
        }

        $payload = CompositeJson::payload($this->mapper, $this->class, $value);

        return $trusted
            ? $this->mapper->fromPayload($this->class, $payload)
            : $this->mapper->fromUntrustedPayload($this->class, $payload);
    }

    /**
     * Shared by every cast built without an explicit mapper: it is stateless.
     */
    private static function defaultMapper(): ValueObjectMapper
    {
        /** @var ValueObjectMapper|null $mapper */
        static $mapper = null;

        return $mapper ??= new ValueObjectMapper();
    }
}
