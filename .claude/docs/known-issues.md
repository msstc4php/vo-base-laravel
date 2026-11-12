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

## Core parsing is strict (vo-base 72d0fc5)

Int payloads must be canonical digits (`"080"` is rejected), float strings must match the core's decimal pattern
(exponent allowed, `"12."` rejected, non-finite rejected), `DateRange` only ISO 8601 with offset. The rule tests pin
`"080"` and `"12."` as failures; the cast tests pin a `DateRange` round trip (stored as
`Y-m-d\TH:i:s.uP`) and reject one without an offset.

## Lowest-deps copy with a non-symlinked path repo goes stale

The `--prefer-lowest` check runs in a `$TMPDIR` copy with `symlink: false`; after a core change, delete
`vendor/msstc4php` and the lock there, or the copy tests an old core.

## Nullable route argument without a default is a 500 (Laravel's behaviour)

`fn (?EmailAddress $email)` on `{email?}` with the segment absent: Laravel drops null parameters
(`parametersWithoutNulls()`), then `transformDependency()` builds the class from the container because the argument
has no default value — `BindingResolutionException`. Laravel's own optional model bindings have the same rule. The
README says to write `?EmailAddress $email = null`; the binder cannot fix it (setting `null` is filtered out).

## Test routes need `SubstituteBindings`

Testbench's `defineRoutes()` routes have no middleware group, so enums and `Route::bind()` are not resolved there.
`RouteBindingTest` wraps its routes in `['middleware' => SubstituteBindings::class]`, like the web/api groups.

## Infection: parallel runs share `sys_get_temp_dir()/infection`

Infection writes its PHPUnit config under `sys_get_temp_dir()`. With other packages' Infection runs at the same time
(same sandbox `TMPDIR`), the initial run fails with "Could not read XML from file". Run it with a private `TMPDIR`:
`TMPDIR=$TMPDIR/inf-laravel XDEBUG_MODE=coverage vendor/bin/infection ...`.

## `#[UsesClass]` for everything an app-booting test executes

`beStrictAboutCoverageMetadata` + `failOnRisky`: under coverage (Infection's initial run), a testbench test is risky
when it runs a package class it does not list. Booting the provider runs `RequestMacros::register()`; dispatching any
route runs `RouteValueObjectBinder`. `make test` (no coverage) does not show it.

## Rector and PHP-CS-Fixer: run `make fix` twice

Rector imports names after cs-fixer has ordered the imports, so a single `make fix` can leave `ordered_imports` for
`make check` to report.

## `--prefer-lowest` resolves Laravel 13.30, not 13.0

`orchestra/testbench` 11.0.0 (the lowest 11.x) needs `laravel/framework` >= 13.30, and the monolith replaces every
`illuminate/*` split, so the lowest-deps job (2026-11-12 UTC) tests the cast and the rule on 13.30.0 too. Runtime
constraints stay `^13.0`; 0.1.0 had verified 13.0.0 before testbench was a dev dependency.
