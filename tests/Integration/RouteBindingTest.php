<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Integration;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use LogicException;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Http\RequestMacros;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Laravel\Routing\RouteValueObjectBinder;
use MSSTC4PHP\ValueObject\Laravel\Tests\ApplicationTestCase;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\UserController;
use MSSTC4PHP\ValueObject\Laravel\ValueObjectServiceProvider;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Full HTTP round trips through the router, the middleware stack and the exception handler.
 */
#[CoversClass(RouteValueObjectBinder::class)]
#[UsesClass(RequestMacros::class)]
#[UsesClass(ValueObjectServiceProvider::class)]
#[UsesClass(TypeLabel::class)]
final class RouteBindingTest extends ApplicationTestCase
{
    public function testRouteParameterBecomesTheValueObject(): void
    {
        $this->get('/users/jane@example.com')->assertOk()->assertContent('jane@example.com');
    }

    public function testIntBackedRouteParameter(): void
    {
        $this->get('/ports/8080')->assertOk()->assertContent('8080');
    }

    public function testInvalidRouteParameterIsNotFound(): void
    {
        $html = $this->get('/users/nope');
        $json = $this->getJson('/users/nope');

        $html->assertNotFound();
        $json->assertNotFound()->assertExactJson(['message' => 'The email route parameter is not a valid email address.']);
        self::assertStringNotContainsString('MSSTC4PHP', (string) $html->baseResponse->getContent());
        self::assertStringNotContainsString('MSSTC4PHP', (string) $json->baseResponse->getContent());
    }

    public function testSnakeCaseRouteParameterBindsTheCamelCaseArgument(): void
    {
        $this->get('/snake/jane@example.com')->assertOk()->assertContent('jane@example.com');
    }

    public function testOptionalParameterAbsentIsNull(): void
    {
        $this->get('/optional')->assertOk()->assertContent('none');
        $this->get('/optional/jane@example.com')->assertOk()->assertContent('jane@example.com');
    }

    public function testExplicitBindingIsLeftAloneAndItsValueObjectPassesThrough(): void
    {
        $this->get('/bound/jane')->assertOk()->assertContent('jane@example.com');
    }

    public function testBackedEnumIsStillBoundByLaravel(): void
    {
        $this->get('/countries/DE')->assertOk()->assertContent('DE');
        $this->get('/countries/XX')->assertNotFound();
    }

    public function testUnionTypeIsUntouched(): void
    {
        $this->get('/union/jane@example.com')->assertOk()->assertContent('string jane@example.com');
    }

    public function testTrustedSourceOnlyClassIsAProgrammingError(): void
    {
        $this->withoutExceptionHandling();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$name');

        $this->get('/bounded/x');
    }

    public function testControllerActionWorksLikeAClosure(): void
    {
        $this->get('/controller/jane@example.com')->assertOk()->assertContent('jane@example.com');
        $this->get('/controller/nope')->assertNotFound();
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('app.debug', false);
    }

    /**
     * SubstituteBindings is in the web and api groups of every application; Laravel binds enums and explicit
     * bindings there, after the RouteMatched event this package listens to.
     */
    #[Override]
    protected function defineRoutes($router): void
    {
        $router->bind('bound', static fn (string $value): EmailAddress => EmailAddress::from($value . '@example.com'));

        $router->group(['middleware' => SubstituteBindings::class], static function (Router $router): void {
            $router->get('/users/{email}', static fn (EmailAddress $email): string => $email->value);
            $router->get('/ports/{port}', static fn (Port $port): string => (string) $port->value);
            $router->get('/snake/{user_email}', static fn (EmailAddress $userEmail): string => $userEmail->value);
            $router->get('/optional/{email?}', static fn (?EmailAddress $email = null): string => $email->value ?? 'none');
            $router->get('/countries/{country}', static fn (CountryCode $country): string => $country->value);
            $router->get('/union/{id}', static fn (EmailAddress|string $id): string => \is_string($id) ? 'string ' . $id : 'value object');
            $router->get('/bounded/{name}', static fn (BoundedString $name): string => $name->value);
            $router->get('/controller/{email}', [UserController::class, 'show']);
            $router->get('/bound/{bound}', static fn (EmailAddress $bound): string => $bound->value);
        });
    }
}
