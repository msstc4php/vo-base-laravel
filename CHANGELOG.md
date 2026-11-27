# Changelog

## [1.0.0] - unreleased

First stable release: from here on a breaking change needs 2.0. What 1.x covers is listed in the README,
"Backward compatibility": the classes and the trait, the constructors of the cast and the rule with their parameter
names, the cast definition, the `valueObject` macro, the container id, the route binding rules, the translation key
and its `:attribute` / `:type` placeholders, the stored formats, and which exception each path throws. Not covered:
message wording otherwise and anything `@internal`.

### Changed

- Requires `msstc4php/vo-base` ^1.4 (was ^1.1): an abstract class implementing `ResolvesConcreteClass` is a valid
  cast, rule, request input and route parameter type, and a mapper built from a `ProvidesCodecs` set
  (`new ValueObjectMapper(Codecs::all())`) bound in the container reaches the cast, the rule, the request input and
  the route binding; both are tested.
- Requires Laravel 13.40+: `illuminate/*` `^13.40` (was `^13.30`). `laravel/framework` below 13.40 has a security
  advisory, so the lowest version CI can install is 13.40. `laravel/framework`, `illuminate/http` and
  `illuminate/routing` below 13.40 now conflict.
- `Http\RequestMacros` and `Routing\RouteValueObjectBinder` are `@internal`: the provider registers them.

### Upgrading from 0.x

- No change to the cast, the rule, the trait, the macro, the route binding or the messages.
- Update `msstc4php/vo-base` to ^1.4 and Laravel to 13.40 or later if they are pinned lower.
- Code that called `RequestMacros::register()` or built a `RouteValueObjectBinder` itself should rely on
  `ValueObjectServiceProvider` instead; neither is covered by 1.x (`RequestMacros` also has a private constructor
  now).

## 0.3.0 - 2026-11-23

- `Casts\AsValueObject` and `Rules\ValidValueObject` built without a mapper use the container's `ValueObjectMapper`
  binding, like the request macro, the form request trait and the route binding already did; before, they used a
  plain mapper of their own, so a codec bound in the container (vo-documents' composite names, an application's own
  composites) needed a `Castable` per class. Without a binding a plain mapper is used, as before. Casters are cached
  per class and per mapper, so a rebinding gets casters of its own.
- `ValidValueObject`'s `$mapper` parameter is now nullable (null: the container's binding); passing one is unchanged.
- Requires `illuminate/container` (already installed through `illuminate/database`).

## 0.2.0 - 2026-11-12

- `ValueObjectServiceProvider`, auto-discovered (`extra.laravel.providers`): binds a shared `ValueObjectMapper`
  singleton (kept if the application bound one first; rebind it to add codecs) and registers the request macro and
  the route binding when `illuminate/http` / `illuminate/routing` are installed.
- `$request->valueObject($key, Foo::class)` (`Http\RequestMacros`): `null` when not filled, a `ValidationException`
  (422, the rule's message for that key) when invalid or trusted-source only, `LogicException` for an unsupported
  class. Composites from an array or JSON text.
- `Http\ValidatedValueObjects`: a form request trait with the same `valueObject()` over `validated()`.
- `Routing\RouteValueObjectBinder`: a `RouteMatched` listener turning route parameters into the scalar-backed value
  objects the action arguments are typed with (name or snake_case name); invalid → 404 without a class name. Leaves
  enums, explicit bindings, instances and union types to Laravel; composite or trusted-source-only argument types
  are a `LogicException`, whether or not the segment is present. Runs before all route middleware.
- Field names in the new messages follow Laravel's validator: form request `attributes()`, then
  `validation.attributes.<key>`, then the key in words (`userEmail` → "user email").
- An application `valueObject` macro on `Request` registered before the provider boots is kept.
- The rule's `:type` label moved to an internal helper shared with the new messages; behaviour unchanged.
- Requires Laravel 13.30+: `illuminate/*` `^13.30` (was `^13.0`), the lowest version the test suite proves.
  `laravel/framework` `^13.30` is suggested for the HTTP features.

## 0.1.0 - 2026-11-11

First release. No stability promise before 1.0.

- `Casts\AsValueObject`: Eloquent cast (`CastsAttributes`, `Castable`, `SerializesCastableAttributes`,
  `ComparesCastableAttributes`) for every class the core's `ValueObjectMapper` supports; composites stored as JSON.
  `AsValueObject::of(EmailAddress::class)` builds the cast definition.
- `Rules\ValidValueObject`: `ValidationRule` delegating to the value object; refuses classes the core marks as
  trusted-source only (`BoundedString`).
- A raw payload assigned to a cast attribute is untrusted input (`fromUntrustedPayload()`; a raw `BoundedString` is an
  `InvalidPayloadException`); stored values are read as trusted (`fromPayload()`). Query expressions are rejected.
- The rule accepts an instance of the value object and, for composites, JSON text; `:type` is a lowercase label
  ("email address"), never a class name.
- Casters are cached per class and share one default mapper.
- Requires `msstc4php/vo-base` `^1.1` and `illuminate/*` `^13.0`.
