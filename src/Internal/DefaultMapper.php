<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Internal;

use Illuminate\Container\Container;
use LogicException;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;

/**
 * The mapper of a cast or rule built without one: the container's binding when there is one (the service provider
 * binds it, an application rebinds it with its codecs), else one plain mapper shared by all (Capsule without a
 * container binding).
 *
 * @internal
 */
final class DefaultMapper
{
    private static ?ValueObjectMapper $fallback = null;

    private function __construct()
    {
    }

    /**
     * @throws LogicException when the binding resolves to something else than a ValueObjectMapper
     */
    public static function get(): ValueObjectMapper
    {
        $container = Container::getInstance();
        if (!$container->bound(ValueObjectMapper::class)) {
            return self::$fallback ??= new ValueObjectMapper();
        }

        $mapper = $container->make(ValueObjectMapper::class);

        return $mapper instanceof ValueObjectMapper ? $mapper : throw new LogicException(\sprintf('The container binds %s to %s.', ValueObjectMapper::class, get_debug_type($mapper)));
    }
}
