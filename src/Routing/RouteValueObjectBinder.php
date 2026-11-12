<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Routing;

use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use LogicException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A route parameter becomes the value object its action argument is typed with: `/users/{email}` into
 * `EmailAddress $email`, an invalid value is a 404. Scalar-backed value objects only: a route segment is one string.
 *
 * Listens to RouteMatched, which fires before the middleware stack, so SubstituteBindings still binds enums
 * (Laravel's own) and parameters with an explicit `Route::bind()` / `Route::model()`, which are left alone here.
 * Not `Router::substituteImplicitBindingsUsing()`: that is a single slot an application may already use.
 */
final readonly class RouteValueObjectBinder
{
    public function __construct(
        private ValueObjectMapper $mapper,
        private Router $router,
    ) {
    }

    /**
     * @throws NotFoundHttpException when a parameter is not a valid value of its value object
     * @throws LogicException when an argument is typed with a value object a route segment cannot carry
     */
    public function __invoke(RouteMatched $event): void
    {
        $route = $event->route;
        $parameters = $route->parameters();

        foreach ($route->signatureParameters() as $argument) {
            $type = $argument instanceof ReflectionParameter ? $argument->getType() : null;
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();
            $name = $argument->getName();
            $key = \array_key_exists($name, $parameters) ? $name : Str::snake($name);
            if (enum_exists($class) || !$this->mapper->supports($class) || !\array_key_exists($key, $parameters) || $this->router->getBindingCallback($key) !== null) {
                continue;
            }

            $value = $parameters[$key];
            if ($value === null || $value instanceof $class) {
                continue;
            }

            if (!$this->mapper->supportsUntrusted($class)) {
                throw new LogicException(\sprintf('Route parameter $%s is typed %s, which cannot be read from request input: its payload carries its own constraints.', $name, $class));
            }

            if (!$this->mapper->isScalarBacked($class)) {
                throw new LogicException(\sprintf('Route parameter $%s is typed %s, a composite value object; a route segment is one string, so only scalar-backed value objects bind.', $name, $class));
            }

            try {
                $route->setParameter($key, $this->mapper->fromUntrustedPayload($class, $value));
            } catch (InvalidValueObjectException $exception) {
                throw new NotFoundHttpException(\sprintf('The %s route parameter is not a valid %s.', $key, TypeLabel::of($class)), $exception);
            }
        }
    }
}
