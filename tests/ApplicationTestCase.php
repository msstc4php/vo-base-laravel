<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LogicException;
use MSSTC4PHP\ValueObject\Laravel\ValueObjectServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;

/**
 * A Laravel application with the package's service provider registered, as package discovery would do.
 */
abstract class ApplicationTestCase extends TestCase
{
    /**
     * @return list<class-string<ServiceProvider>>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [ValueObjectServiceProvider::class];
    }

    protected function application(): Application
    {
        return $this->app ?? throw new LogicException('The application is not booted.');
    }
}
