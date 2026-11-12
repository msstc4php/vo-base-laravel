# Architecture

Thin adapters over the core's `MSSTC4PHP\ValueObject\Payload\ValueObjectMapper`:

| Area | Class | Laravel side |
|---|---|---|
| `Casts` | `AsValueObject` | Eloquent attribute casting (`illuminate/database`) |
| `Rules` | `ValidValueObject` | Request validation (`illuminate/validation`) |
| `Http` | `RequestMacros`, `ValidatedValueObjects` (trait), `ValueObjectInput` (`@internal`) | `Request::macro('valueObject')`, `FormRequest` |
| `Routing` | `RouteValueObjectBinder` | `RouteMatched` listener (`illuminate/routing`) |
| `Internal` | `TypeLabel`, `FieldName`, `CompositeJson` (all `@internal`) | client-facing labels ("email address", "user email"); composite JSON-text decoding for every input path |
| `Provider` | `ValueObjectServiceProvider` | auto-discovered (`extra.laravel.providers`) |

- `deptrac.yaml`: `Casts` → `Internal` (CompositeJson); `Rules` → `Internal`; `Http` → `Rules` (the message),
  `Internal`; `Routing` → `Internal`; `Provider` → `Http`, `Routing`. The core and Illuminate are not layers and
  therefore unrestricted.
- Kind detection (string/int/float/enum/composite, trusted-only) lives only in the mapper.
- Two ways to get a mapper:
  - the cast and the rule take one as an argument (default: a new/shared `ValueObjectMapper`); no container;
  - the macro, the trait and the binder resolve the container singleton the provider binds (`singletonIf`, so an
    earlier application binding wins; a later one replaces it). The binder is registered as a class-string listener,
    so it is resolved per event and sees a rebinding made after boot.
- `ValueObjectInput` is the shared null / invalid → `ValidationException` logic of the macro and the trait.
  `CompositeJson::payload()` is the single JSON-text decode (cast, rule, input); invalid JSON is an
  `InvalidPayloadException` (code = JSON error code, previous = `JsonException`), so callers catch only
  `InvalidValueObjectException`. `ValidValueObject::DEFAULT_MESSAGE` is the message of all three input paths.
- The provider registers the macro only if `Illuminate\Http\Request` exists and the listener only if
  `Illuminate\Routing\Router` exists (`callAfterResolving`, so the router is not resolved early). Runtime `require`
  stays the `illuminate/*` splits; `laravel/framework` is dev-only + `suggest` (`FormRequest` lives only there).
