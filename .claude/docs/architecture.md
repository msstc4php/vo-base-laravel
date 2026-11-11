# Architecture

Two independent areas, both thin adapters over the core's `MSSTC4PHP\ValueObject\Payload\ValueObjectMapper`:

| Area | Class | Laravel side |
|---|---|---|
| `Casts` | `AsValueObject` | Eloquent attribute casting (`illuminate/database`) |
| `Rules` | `ValidValueObject` | Request validation (`illuminate/validation`) |

- `deptrac.yaml` declares both as layers with an empty ruleset: neither may use the other. The core and Illuminate
  are not layers and therefore unrestricted.
- Kind detection (string/int/float/enum/composite) lives only in the mapper. The bridge adds transport: JSON text for
  composites in SQL, Laravel's message plumbing for the rule.
- No service provider: a cast is resolved from the cast definition string, a rule is instantiated by the caller.
  Nothing needs the container.
- Both classes take an optional `ValueObjectMapper`, so codecs for user composites can be plugged in; the string cast
  definition always uses the default mapper (Laravel instantiates it).
