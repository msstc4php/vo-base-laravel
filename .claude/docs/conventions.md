# Conventions

## Raw payload assignment is accepted on purpose

`$model->email = 'a@b.c'` works: every non-instance value goes through `ValueObjectMapper::fromPayload()`, so it is
validated (and canonicalised) on assignment. A value object of another class is just "a payload of the wrong type"
and fails with `InvalidPayloadException`. Rejecting raw values would break `fill()`/`create()` from request data.

## JSON decoding is decided by `isComposite()`

`toValueObject()` decodes a string as JSON only when `ValueObjectMapper::isComposite($class)` (the class has a codec;
core 8c795e1). Invalid JSON is an `InvalidPayloadException` (code = the `JsonException` code). The bridge never names
value-object kinds itself; `JsonString` columns stay strings because `JsonString` has no codec.

## Caching: casters per class, one default mapper

Eloquent re-resolves the caster on every attribute access, so `castUsing()` keeps one caster per class in a
function-static array and every cast built without an explicit mapper shares one default mapper (function-static in
`private static function defaultMapper()`). Rector turns a static method that is only called from instance context
into an instance method, so `castUsing()` passes `self::defaultMapper()` explicitly, which keeps it static. Both are
stateless.

## `castUsing()` checks `is_a(..., ValueObject|BackedEnum)` before the constructor

Needed so PHPStan can narrow the untyped definition argument to a class-string; the constructor's `supports()` check
still decides. The two messages differ ("expects exactly one value object class" vs "cannot cast to"), and the tests
pin both — infection kills the boolean mutants only that way.

## Trust boundary: storage is trusted, assignment and requests are not

`BoundedString`'s payload includes `minLength`/`maxLength`; from request input the client would pick the bounds. The
core marks such codecs `TrustedSourceOnly` (vo-base 72d0fc5); the bridge never names the class itself:

- `AsValueObject::get()`, `serialize()`, `compare()` read storage: `fromPayload()`.
- `AsValueObject::set()` with a raw (non-instance) value: `fromUntrustedPayload()`, because `fill($request->all())`
  reaches it. For a trusted-source-only class the core throws `InvalidPayloadException` (a client error, the family
  contract); `LogicException` only for an unsupported class. Assigning an instance is always fine.
- `ValidValueObject` still refuses a trusted-source-only class with `LogicException` at construction: that is a
  configuration mistake, not input.
- `ValidValueObject`: `supportsUntrusted()` at construction, `fromUntrustedPayload()` in `validate()`.

Coordinator decision 2026-11-11 UTC, applied in every bridge.

## Tests

- All tests live in `tests/Unit`, including the SQLite `:memory:` Eloquent round trips: infection runs only the unit
  suite (`infection.json5`), and they are fast.
- Eloquent is booted with `Illuminate\Database\Capsule\Manager` per test; no orchestra/testbench.
- The validator is `Illuminate\Validation\Factory` over `Translator(new ArrayLoader(), 'en')`. JSON translations in
  tests: `$loader->addMessages($locale, '*', [...], '*')`.
- `tests/Fixtures/Priority` exists because the core ships no int-backed enum.
- `tests/Fixtures/Dimensions` + `DimensionsCodec` + `AsDimensions` test the README's custom-codec recipe for both the
  cast and the rule.
