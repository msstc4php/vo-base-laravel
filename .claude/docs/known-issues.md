# Known issues and quirks

## Laravel 12 cannot be supported

Every `illuminate/database` / `illuminate/validation` 12.x release (checked up to 12.72.0, 2026-11-11 UTC) requires
`brick/math` `^0.11 … ^0.14`, and `msstc4php/vo-base` declares `conflict: brick/math <0.15`. No Laravel 12 install can
satisfy both, so the package requires `^13.0`; `--prefer-lowest` resolves to 13.0.0, which passes the suite. Revisit
if a 12.x release widens `brick/math` or the core relaxes its conflict.

## `Model::$casts` override fails PHPStan

The parent property is untyped `array`; a narrower `@var array<string, string>` on the override is reported as
`property.phpDocType` (invariant), and no PHPDoc gives `missingType.iterableValue`. Test fixtures therefore declare
casts in `casts(): array` with `@return array<string, string>`. `[Class, arg]` array definitions work at runtime but
do not fit Model's `array<string, string|Stringable>` return PHPDoc.

## `json_encode` without `JSON_PRESERVE_ZERO_FRACTION`

`GeoCoordinates::fromFloats(1, 2)` is stored as `{"lat":1,"lng":2}`, the same bytes as the Doctrine type. The codec
reads ints back as floats, so the value round-trips.

## Infection locally: "absolute path cannot be made relative"

`infection.json5` enables the GitHub logger. Run locally with escaped mutants present, Infection prints the metrics
and then fails in `Path.php` building the GitHub annotation paths (no `GITHUB_WORKSPACE`). With no escaped mutants
the run exits 0. Read `var/infection/infection.log` for the escapes.

## Translator keys with dots

The default message ends with a `.`; `Translator::get()` still finds it in JSON translations (looked up verbatim
before group parsing) and otherwise returns it unchanged with replacements applied.
