# Integrations

## Eloquent cast — `AsValueObject`

Implements `Castable`, `CastsAttributes`, `SerializesCastableAttributes`, `ComparesCastableAttributes`.

How Eloquent drives it (`Illuminate\Database\Eloquent\Concerns\HasAttributes`, Laravel 13):

- `resolveCasterClass()` splits `Class:arg1,arg2` on the first `:` and `,`. A `Castable` class gets
  `castUsing($arguments)`; any other class gets `new $class(...$arguments)`. The caster is **re-resolved on every
  access** (Eloquent does not cache it), hence the per-class cache in `castUsing()`.
- An `Illuminate\Database\Query\Expression` assigned to the attribute reaches `set()` like any value; the cast
  rejects it as a wrong payload type.
- `casts()` may return strings, `[Class, arg]` arrays (joined to `Class:arg`) or `Stringable` objects. Model's
  PHPDoc only declares `array<string, string|Stringable>`, so `AsValueObject::of()` (a string) is the documented form.
- `get()` result objects are cached in `classCastCache`; on save `mergeAttributesFromClassCasts()` calls `set()` again
  with the cached object.
- `set()` returning a non-array is stored under the attribute key. Assigning a non-object (raw payload) clears the
  object cache, so the next read goes through `get()`.
- `toArray()` calls `serialize()` (because of `SerializesCastableAttributes`) with the cached/cast value.
- Dirty checking: `originalIsEquivalent()` uses `compare()` for `ComparesCastableAttributes` casters. Without it a
  composite would be dirty whenever the DB reformats JSON.

Stored formats (identical to the Doctrine package, same `json_encode` flags — only `JSON_THROW_ON_ERROR`):

| Kind | Stored |
|---|---|
| string-backed | string |
| int-backed / int enum | int |
| float-backed | float (`1.0` encodes as `1` inside composites: no `JSON_PRESERVE_ZERO_FRACTION`) |
| string enum | string |
| composite | JSON object text |

## Validation — `ValidValueObject`

`ValidationRule::validate($attribute, $value, $fail)`. `$fail($message)` returns a `PotentiallyTranslatedString`;
`->translate(['type' => ...])` runs `Translator::get()`, which looks the message text up in the JSON translations
(`lang/<locale>.json`) and returns the text itself with replacements when there is none. The validator then
replaces `:attribute` in the result.

`:type` is a label, not a class name: the short name split before every capital that follows a lowercase letter or
digit, lowercased (same scheme as the Symfony bundle's constraint). Composite input that is a string is decoded as
JSON first (as in the cast); invalid JSON is a rule failure.

Laravel itself does not run a non-implicit rule for `''` (and for missing attributes); the rule still lets
`null`/`''` through for direct calls.

## Request macro — `Request::valueObject()`

- `Request` uses `Macroable`; `Macroable::macro()` is annotated `@param-closure-this static`, so PHPStan types
  `$this` in the closure as `Request` without a `@var`. The closure is created in a static method; PHP still binds it
  (`Closure::bind` in `Macroable::__call`), only an explicitly `static` closure cannot be bound.
- PHPStan does not apply `@template` / `@param` PHPDoc written on a closure, so `$class` is narrowed with
  `class_exists()` (also true for enums) inside the macro.
- Mapper and translator: `Container::getInstance()`; `Request` has no container reference.
- The message is `Translator::get(DEFAULT_MESSAGE, [attribute, type])`, which looks the text up in JSON translations
  and replaces both placeholders itself. The validator's custom attribute names (`validation.attributes`) are not
  applied: there is no validator here. `_` in the key becomes a space, as the validator displays attributes.
- `ValidationException::withMessages()` gives status 422; it has no way to chain the core exception (no previous),
  and it needs the Validator facade, so even a direct `ValueObjectInput` test needs a booted application.
- `Translator::get()` may return an array (a translation group named like the key); the fallback is
  `DEFAULT_MESSAGE` with `:attribute`/`:type` replaced by `strtr`.

## Form requests — `ValidatedValueObjects`

- `@phpstan-require-extends FormRequest`, so `$this->container` and `validated()` are known.
- `validated($key)` returns `null` for a key outside `rules()`. A trait method beats the macro of the same name
  (`__call` only sees undefined methods).
- `ValidatedInput` (`safe()`) is not `Macroable`: no `safe()->valueObject()`.

## Route parameters — `RouteValueObjectBinder`

Laravel 13 order inside `Router::runRoute()`: `RouteMatched` is dispatched, then the route middleware run;
`SubstituteBindings` (web/api groups) does explicit bindings (`Router::substituteBindings()`, binders keyed with
`-` → `_`) and then implicit ones (`ImplicitRouteBinding`: backed enums first, then `UrlRoutable`).

- The binder runs first, so it must skip parameters with `Router::getBindingCallback($key)`: otherwise a user's
  `Route::bind('email', fn (string $v) => ...)` would receive our value object instead of the string.
- Parameter lookup mirrors `ImplicitRouteBinding::getParameterName()`: exact name, then `Str::snake($name)`.
- Laravel passes route parameters to the action positionally (`resolveMethodDependencies` + `array_values`); a
  class-typed argument is not built from the container when an instance of it is already among the parameters.
- An absent optional parameter is not in `parameters()` at all (`RouteParameterBinder` drops empty matches).
- `Route::signatureParameters()` is untyped (`array`), hence the `instanceof ReflectionParameter` narrowing.
- `NotFoundHttpException`'s message reaches the client: the JSON 404 is `{"message": "..."}` even with
  `app.debug=false`, so it carries the parameter name (`FieldName`, `_` → space, as the 422 path) and the type label
  only.
- Because the listener runs before all route middleware, an invalid segment is a 404 before `auth` can answer
  401/403, and middleware reading `$request->route('email')` gets the value object.
