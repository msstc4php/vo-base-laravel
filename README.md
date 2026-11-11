# msstc4php/vo-base-laravel

Laravel glue for [`msstc4php/vo-base`][core] value objects:

- `Casts\AsValueObject` — an Eloquent cast for every value object the core's `ValueObjectMapper` supports.
- `Rules\ValidValueObject` — a validation rule that asks the value object whether the input is valid.

No service provider: nothing needs registering.

Requires PHP >= 8.4 and Laravel 13 (`illuminate/database`, `illuminate/validation` `^13.0`). Laravel 12 cannot be
supported: every 12.x release caps `brick/math` below 0.15, which the core declares as a conflict.

## Install

Neither this package nor the core is on Packagist yet, so register both GitHub repositories:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base" },
        { "type": "vcs", "url": "https://github.com/msstc4php/vo-base-laravel" }
    ],
    "require": {
        "msstc4php/vo-base-laravel": "^0.1"
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

## Development

```
COMPOSER=composer-local.json composer update --ignore-platform-req=php   # against ../vo-base
make check   # php -l, PHPStan level max, cs-fixer, composer validate/audit, Rector, deptrac
make test    # PHPUnit (Eloquent on SQLite :memory:, the real validator)
make infection
```

[core]: https://github.com/msstc4php/vo-base
