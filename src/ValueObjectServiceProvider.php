<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use MSSTC4PHP\ValueObject\Laravel\Http\RequestMacros;
use MSSTC4PHP\ValueObject\Laravel\Routing\RouteValueObjectBinder;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;

/**
 * Auto-discovered. Shares one mapper; an application adds codecs by binding its own
 * `ValueObjectMapper` singleton, before or after this provider registers.
 */
final class ValueObjectServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singletonIf(ValueObjectMapper::class, static fn (): ValueObjectMapper => new ValueObjectMapper());
    }

    public function boot(): void
    {
        // illuminate/http and illuminate/routing are optional: an Eloquent-only install (Capsule) has neither.
        if (class_exists(Request::class)) {
            RequestMacros::register();
        }

        if (class_exists(Router::class)) {
            // A class-string listener is resolved per event, so a mapper rebound after boot still applies.
            $this->callAfterResolving(Router::class, static function (Router $router): void {
                $router->matched(RouteValueObjectBinder::class);
            });
        }
    }
}
