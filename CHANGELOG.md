# Changelog

## 0.1.0 - 2026-11-11

First release. No stability promise before 1.0.

- `Casts\AsValueObject`: Eloquent cast (`CastsAttributes`, `Castable`, `SerializesCastableAttributes`,
  `ComparesCastableAttributes`) for every class the core's `ValueObjectMapper` supports; composites stored as JSON.
  `AsValueObject::of(EmailAddress::class)` builds the cast definition.
- `Rules\ValidValueObject`: `ValidationRule` delegating to the value object; refuses classes the core marks as
  trusted-source only (`BoundedString`).
- A raw payload assigned to a cast attribute is untrusted input (`fromUntrustedPayload()`; a raw `BoundedString` is an
  `InvalidPayloadException`); stored values are read as trusted (`fromPayload()`). Query expressions are rejected.
- The rule accepts an instance of the value object.
- Casters are cached per class and share one default mapper.
- Requires `msstc4php/vo-base` `^1.1` and `illuminate/*` `^13.0`.
