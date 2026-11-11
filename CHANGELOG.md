# Changelog

## 0.1.0 - 2026-11-11

First release. No stability promise before 1.0.

- `Casts\AsValueObject`: Eloquent cast (`CastsAttributes`, `Castable`, `SerializesCastableAttributes`,
  `ComparesCastableAttributes`) for every class the core's `ValueObjectMapper` supports; composites stored as JSON.
  `AsValueObject::of(EmailAddress::class)` builds the cast definition.
- `Rules\ValidValueObject`: `ValidationRule` delegating to the value object; refuses classes the core marks as
  trusted-source only (`BoundedString`).
- A raw payload assigned to a cast attribute is untrusted input (`fromUntrustedPayload()`); stored values are read as
  trusted (`fromPayload()`).
- Requires `msstc4php/vo-base` `^1.1` and `illuminate/*` `^13.0`.
