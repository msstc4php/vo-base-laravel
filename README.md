# msstc4php/vo-base-laravel

Laravel glue for [`msstc4php/vo-base`][core] value objects:

- `Casts\AsValueObject` — an Eloquent cast for every value object the core's `ValueObjectMapper` supports.
- `Rules\ValidValueObject` — a validation rule that asks the value object whether the input is valid.
- `$request->valueObject('email', EmailAddress::class)` — request input as a value object, invalid input a 422.
- `Http\ValidatedValueObjects` — the same for a form request, over its validated data.
- Route parameters typed with a value object (`/users/{email}` into `EmailAddress $email`), invalid values a 404.

The last three come from `ValueObjectServiceProvider`, which Laravel's package discovery registers.

Requires PHP >= 8.4 and Laravel 13 (`illuminate/database`, `illuminate/validation` `^13.0`). The cast and the rule
work with those components alone (Capsule included); request input, form requests and route parameters need an
application on `laravel/framework`. Laravel 12 cannot be supported: every 12.x release caps `brick/math` below 0.15,
which the core declares as a conflict.

## Install

Neither this package nor the core is on Packagist yet, so register both GitHub repositories:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base" },
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base-laravel" }
    ],
    "require": {
        "msstc4php/vo-base-laravel": "^0.2"
    }
}
```

## Eloquent cast

```php
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;

final class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'email' => AsValueObject::of(EmailAddress::class),
            'country' => AsValueObject::of(CountryCode::class),
            'location' => AsValueObject::of(GeoCoordinates::class),
        ];
    }
}
```

`AsValueObject::of(X::class)` is the string `AsValueObject::class.':'.X::class`, so the `$casts` property form works
the same way: `protected $casts = ['email' => AsValueObject::class.':'.EmailAddress::class];`.

| Value object kind | Example | Stored as | Column |
|---|---|---|---|
| string-backed | `EmailAddress`, `Uuid` | the string | `string` / `text` |
| int-backed | `Port`, `PositiveInt` | the integer | `integer` |
| float-backed | `Percentage` | the number | `float` / `double` |
| backed enum | `CountryCode`, `CurrencyCode` | the enum code (`->value`) | `string` / `integer` |
| composite | `GeoCoordinates`, `IntRange`, `DateRange`, `BoundedString` | JSON text, e.g. `{"lat":52.52,"lng":13.405}` | `text` / `json` |

The formats are those of the core's payload layer, so a column written by this cast reads back through the
`msstc4php/vo-base-doctrine` types and the other bridges.

Behaviour:

- `null` stays `null` both ways.
- Reading a stored value that is not valid throws (`InvalidValueObjectException` or its subclass
  `InvalidPayloadException`); it never turns into `null`. Numeric strings from the driver are accepted for int- and
  float-backed value objects and int enums.
- Assigning a value object stores its payload. Assigning a raw payload is accepted too and validated through the
  value object: `$customer->email = 'jane@example.com'` stores the canonical form, `$customer->port = 70000` throws.
  A composite accepts an array or its JSON text. A raw payload counts as untrusted input (it may come from
  `$model->fill($request->all())`): `BoundedString`, whose payload carries its own bounds, must be assigned as an
  instance — a raw array or JSON for it throws `InvalidPayloadException`. Reading it from the database is fine.
- Query expressions (`DB::raw(...)`, any `Expression`) are not payloads: assigning one throws
  `InvalidPayloadException`. Write such updates with the query builder instead.
- `toArray()` / `toJson()` use the payload form: a composite becomes a nested array, not a JSON string.
- Dirty checking compares payloads, so a `jsonb` column that reformats the JSON does not make the model dirty.
- An unsupported class (`AsValueObject::of(stdClass::class)`) throws `LogicException` the first time the model uses
  the cast.

For a value object of your own with a payload codec, give the cast your mapper through a small `Castable`:

```php
final readonly class AsMoney implements Castable
{
    public static function castUsing(array $arguments): AsValueObject
    {
        return new AsValueObject(Money::class, new ValueObjectMapper([new MoneyCodec()]));
    }
}

// casts(): 'price' => AsMoney::class
```

The rule takes the same mapper: `new ValidValueObject(Money::class, mapper: $mapper)`.

## Validation rule

```php
use MSSTC4PHP\ValueObject\Laravel\Rules\ValidValueObject;

$request->validate([
    'email' => ['required', new ValidValueObject(EmailAddress::class)],
    'port' => ['nullable', new ValidValueObject(Port::class)],
    'country' => [new ValidValueObject(CountryCode::class, 'Pick a country from the list.')],
]);
```

- `null` and `''` pass; emptiness is the job of `required`.
- The input goes through the same mapper as the cast: form strings like `"8080"` pass for `Port`, JSON numbers too.
  Composites accept an array (`{"lat": 1, "lng": 2}` in a JSON body) or JSON text (a form field), exactly like the
  cast; invalid JSON fails the rule. An instance of the value object passes as well.
- Invalid content and a wrong input type both fail with one message: `The :attribute field is not a valid :type.`,
  where `:type` is the short class name as lowercase words (`EmailAddress` → "email address", `Ipv4Address` →
  "ipv4 address"), never a class name. The text is also the translation key: add it to
  `lang/<locale>.json` to translate it. A custom message (second argument) is translated the same way.
- A class the core marks as trusted-source only (`ValueObjectMapper::supportsUntrusted()` is false — today
  `BoundedString`) is refused with a `LogicException`: its payload carries its own length bounds, so request input
  would choose the limits it is checked against. Validate length with `min`/`max` or use `NonEmptyString`.
- An unsupported class throws `LogicException` in the constructor.

## Request input

```php
public function store(Request $request)
{
    $email = $request->valueObject('email', EmailAddress::class);      // EmailAddress|null
    $location = $request->valueObject('location', GeoCoordinates::class);
}
```

- Reads `$request->input($key)`: query string, form fields and JSON body alike (dot notation works).
- Missing, `null` or `''` returns `null`; add `required` validation (or check for `null`) when the field must be there.
- Invalid input throws `ValidationException` for that key, which Laravel answers with 422 and
  `{"errors": {"email": ["The email field is not a valid email address."]}}` (a redirect back with errors for a
  form). The message is the rule's (`ValidValueObject::DEFAULT_MESSAGE`, translated through `lang/<locale>.json`),
  with `_` in the key read as a space. An array where a string is expected is invalid input, not an error page.
  Unlike `$request->enum()`, there is no silent fallback to a default: that would hide a client error.
- Composites take an array (JSON body) or JSON text (a form field or a query string).
- `BoundedString` and any other trusted-source-only class are always a field error: their payload carries its own
  constraints.
- An unsupported class (`stdClass`) is a `LogicException`, whether the field is filled or not.

PHPStan sees the macro only with Larastan; without it, call it from a form request with the trait below.

## Form requests

```php
use MSSTC4PHP\ValueObject\Laravel\Http\ValidatedValueObjects;

final class StoreCustomerRequest extends FormRequest
{
    use ValidatedValueObjects;

    public function rules(): array
    {
        return [
            'email' => ['required', new ValidValueObject(EmailAddress::class)],
            'location' => ['nullable', new ValidValueObject(GeoCoordinates::class)],
        ];
    }
}

public function store(StoreCustomerRequest $request)
{
    $email = $request->valueObject('email', EmailAddress::class);
}
```

- Reads `validated($key)`: the rules have already vetted the value, so a request reaching the controller converts
  cleanly. A key that is not in `rules()` returns `null`, even when the client sent it.
- Same rules as the request macro otherwise (`null` for empty, composites from an array or JSON text), and it is
  typed for PHPStan: `valueObject(string $key, class-string<T> $class): ?T`.
- On a form request using the trait, `valueObject()` is the trait's (validated data), not the request macro.
- `$request->safe()` returns Laravel's `ValidatedInput`, which is not macroable, so there is no
  `safe()->valueObject()`.

## Route parameters

```php
Route::get('/users/{email}', fn (EmailAddress $email) => User::where('email', $email->value)->firstOrFail());
Route::get('/teams/{team_slug}', [TeamController::class, 'show']); // show(Slug $teamSlug)
Route::get('/ports/{port?}', fn (?Port $port = null) => $port?->value ?? 80);
```

- An action argument (closure or controller method) typed with a scalar-backed value object receives it, built from
  the route parameter of the same name or its snake_case form, as Laravel's implicit binding matches them.
- An invalid value is a 404, with the message `The email route parameter is not a valid email address.`.
- Nullable arguments for optional parameters need a default (`?Port $port = null`), as for Laravel's own bindings:
  without one Laravel tries to build the class from the container.
- Left alone: backed enums (Laravel binds them), parameters with an explicit `Route::bind()` / `Route::model()`
  (an `EmailAddress` your binder returns reaches the argument unchanged), values that already are the value object,
  union and intersection types, and classes the mapper does not support.
- An argument typed with a composite (`GeoCoordinates`) or a trusted-source-only class (`BoundedString`) is a
  `LogicException` naming the argument: a URL segment is one string and must not choose its own constraints.
- The conversion runs on Laravel's `RouteMatched` event, before the route's middleware.

## Custom codecs

The provider binds one `ValueObjectMapper` singleton; the request macro, the form request trait and the route
binding resolve it from the container. Bind your own in a service provider's `register()` to add codecs:

```php
$this->app->singleton(ValueObjectMapper::class, fn () => new ValueObjectMapper([new MoneyCodec()]));
```

The cast and the rule do not use the container (see above for passing them a mapper).

## Development

```
COMPOSER=composer-local.json composer update --ignore-platform-req=php   # against ../vo-base
make check   # php -l, PHPStan level max, cs-fixer, composer validate/audit, Rector, deptrac
make test    # PHPUnit (Eloquent on SQLite :memory:, the real validator, Orchestra Testbench for HTTP)
make infection
```

[core]: https://github.com/msstc4php/vo-base
