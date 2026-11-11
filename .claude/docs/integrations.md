# Integrations

## Eloquent cast — `AsValueObject`

Implements `Castable`, `CastsAttributes`, `SerializesCastableAttributes`, `ComparesCastableAttributes`.

How Eloquent drives it (`Illuminate\Database\Eloquent\Concerns\HasAttributes`, Laravel 13):

- `resolveCasterClass()` splits `Class:arg1,arg2` on the first `:` and `,`. A `Castable` class gets
  `castUsing($arguments)`; any other class gets `new $class(...$arguments)`. The caster is **re-resolved on every
  access** (no caching of the caster object), so construction must stay cheap.
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

Laravel itself does not run a non-implicit rule for `''` (and for missing attributes); the rule still lets
`null`/`''` through for direct calls.
