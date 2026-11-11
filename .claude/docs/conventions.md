# Conventions

## Raw payload assignment is accepted on purpose

`$model->email = 'a@b.c'` works: every non-instance value goes through `ValueObjectMapper::fromPayload()`, so it is
validated (and canonicalised) on assignment. A value object of another class is just "a payload of the wrong type"
and fails with `InvalidPayloadException`. Rejecting raw values would break `fill()`/`create()` from request data.

## JSON fallback instead of kind detection

The bridge does not know whether a class is a composite (the mapper does not expose it, and `BoundedString` is both
`Stringable` and codec-backed). `toValueObject()` tries the raw value first; only when the mapper answers
`InvalidPayloadException` for a string does it decode JSON and retry with an array. A string-backed value object
never throws `InvalidPayloadException` for a string, so `JsonString` columns are not decoded by accident. When the
string is not a JSON object the original exception is rethrown.

## `castUsing()` checks `is_a(..., ValueObject|BackedEnum)` before the constructor

Needed so PHPStan can narrow the untyped definition argument to a class-string; the constructor's `supports()` check
still decides. The two messages differ ("expects exactly one value object class" vs "cannot cast to"), and the tests
pin both — infection kills the boolean mutants only that way.

## Rule refuses `BoundedString`

Its payload includes `minLength`/`maxLength`; from request input the client would pick the bounds. Casts may decode
it (the DB is trusted), the rule may not. Coordinator decision 2026-11-11 UTC, applied in every bridge.

## Tests

- All tests live in `tests/Unit`, including the SQLite `:memory:` Eloquent round trips: infection runs only the unit
  suite (`infection.json5`), and they are fast.
- Eloquent is booted with `Illuminate\Database\Capsule\Manager` per test; no orchestra/testbench.
- The validator is `Illuminate\Validation\Factory` over `Translator(new ArrayLoader(), 'en')`. JSON translations in
  tests: `$loader->addMessages($locale, '*', [...], '*')`.
- `tests/Fixtures/Priority` exists because the core ships no int-backed enum.
