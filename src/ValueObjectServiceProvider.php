<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use MSSTC4PHP\ValueObject\Laravel\Http\RequestMacros;
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
        // illuminate/http is optional: an Eloquent-only install (Capsule) has no requests.
        if (class_exists(Request::class)) {
            RequestMacros::register();
        }
    }
}
