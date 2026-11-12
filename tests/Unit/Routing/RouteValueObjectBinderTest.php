<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Routing;

use Closure;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use LogicException;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Internal\FieldName;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Laravel\Routing\RouteValueObjectBinder;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\DimensionsCodec;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\UnmappedValueObject;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(RouteValueObjectBinder::class)]
#[UsesClass(FieldName::class)]
#[UsesClass(TypeLabel::class)]
final class RouteValueObjectBinderTest extends TestCase
{
    private Router $router;

    #[Override]
    protected function setUp(): void
    {
        $this->router = new Router(new Dispatcher());
    }

    public function testStringParameterBecomesTheValueObject(): void
    {
        $route = $this->bind('/users/{email}', static fn (EmailAddress $email): null => null, '/users/jane@example.com');

        self::assertEquals(EmailAddress::from('jane@example.com'), $route->parameter('email'));
    }

    public function testIntBackedParameter(): void
    {
        $route = $this->bind('/ports/{port}', static fn (Port $port): null => null, '/ports/8080');

        self::assertEquals(Port::from(8080), $route->parameter('port'));
    }

    public function testSnakeCaseParameterNameMatchesTheCamelCaseArgument(): void
    {
        $route = $this->bind('/users/{user_email}', static fn (EmailAddress $userEmail): null => null, '/users/jane@example.com');

        self::assertEquals(EmailAddress::from('jane@example.com'), $route->parameter('user_email'));
        self::assertSame(['user_email'], array_keys($route->parameters()));
    }

    public function testExactNameWinsOverTheSnakeCaseName(): void
    {
        $route = $this->bind('/users/{userEmail}/{user_email}', static fn (EmailAddress $userEmail): null => null, '/users/jane@example.com/nope');

        self::assertEquals(EmailAddress::from('jane@example.com'), $route->parameter('userEmail'));
        self::assertSame('nope', $route->parameter('user_email'));
    }

    public function testInvalidValueIsNotFoundWithoutAClassName(): void
    {
        try {
            $this->bind('/users/{user_email}', static fn (EmailAddress $userEmail): null => null, '/users/nope');
            self::fail('No NotFoundHttpException thrown.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame('The user email route parameter is not a valid email address.', $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function testAbsentOptionalParameterIsNotAddedByTheBinder(): void
    {
        $route = $this->route('/users/{email?}', static fn (?EmailAddress $email = null): null => null, '/users');
        $before = $route->parameters();

        $this->dispatch($route);

        self::assertSame($before, $route->parameters());
        self::assertFalse($route->hasParameter('email'));
    }

    public function testNullParameterStaysNull(): void
    {
        $route = $this->route('/users/{email}', static fn (?EmailAddress $email): null => null, '/users/x');
        $route->setParameter('email', null);

        $this->dispatch($route);

        self::assertNull($route->parameter('email'));
    }

    public function testAnInstanceAlreadyBoundPassesThrough(): void
    {
        $email = EmailAddress::from('jane@example.com');
        $route = $this->route('/users/{email}', static fn (EmailAddress $email): null => null, '/users/x');
        $route->setParameter('email', $email);

        $this->dispatch($route);

        self::assertSame($email, $route->parameter('email'));
    }

    public function testParameterWithAnExplicitBindingIsLeftToIt(): void
    {
        $this->router->bind('user-email', static fn (string $value): EmailAddress => EmailAddress::from($value));
        $route = $this->bind('/users/{user_email}', static fn (EmailAddress $userEmail): null => null, '/users/nope');

        self::assertSame('nope', $route->parameter('user_email'));
    }

    /**
     * @return iterable<string, array{Closure(never, never): null}>
     */
    public static function untouched(): iterable
    {
        yield 'untyped' => [static fn ($id): null => null];
        yield 'builtin' => [static fn (string $id): null => null];
        yield 'backed enum' => [static fn (CountryCode $id): null => null];
        yield 'union' => [static fn (EmailAddress|string $id): null => null];
        yield 'unsupported class' => [static fn (stdClass $id): null => null];
        yield 'value object without payload support' => [static fn (UnmappedValueObject $id): null => null];
    }

    /**
     * @param Closure(never, never): null $action
     */
    #[DataProvider('untouched')]
    public function testParameterIsLeftUntouched(Closure $action): void
    {
        $route = $this->bind('/things/{id}', $action, '/things/XX');

        self::assertSame('XX', $route->parameter('id'));
    }

    /**
     * @return iterable<string, array{string, Closure(never, never): null, string}>
     */
    public static function skippedBeforeABindableOne(): iterable
    {
        yield 'a builtin' => ['/x/{id}/{email}', static fn (string $id, EmailAddress $email): null => null, '/x/1/jane@example.com'];
        yield 'an enum' => ['/x/{id}/{email}', static fn (CountryCode $id, EmailAddress $email): null => null, '/x/DE/jane@example.com'];
        yield 'a value object absent from the route' => ['/x/{email}', static fn (Port $port, EmailAddress $email): null => null, '/x/jane@example.com'];
    }

    /**
     * @param Closure(never, never): null $action
     */
    #[DataProvider('skippedBeforeABindableOne')]
    public function testLaterParametersAreStillBound(string $uri, Closure $action, string $path): void
    {
        self::assertInstanceOf(EmailAddress::class, $this->bind($uri, $action, $path)->parameter('email'));
    }

    public function testParameterAfterANullOneIsStillBound(): void
    {
        $route = $this->route('/x/{id}/{email}', static fn (?Port $id, EmailAddress $email): null => null, '/x/1/jane@example.com');
        $route->setParameter('id', null);

        $this->dispatch($route);

        self::assertInstanceOf(EmailAddress::class, $route->parameter('email'));
    }

    public function testTrustedSourceOnlyClassIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route parameter $name is typed MSSTC4PHP\ValueObject\Primitive\BoundedString, which cannot be read from request input: its payload carries its own constraints.');

        $this->bind('/names/{name}', static fn (BoundedString $name): null => null, '/names/x');
    }

    public function testCompositeIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route parameter $location is typed MSSTC4PHP\ValueObject\Geo\GeoCoordinates, a composite value object; a route segment is one string, so only scalar-backed value objects bind.');

        $this->bind('/places/{location}', static fn (GeoCoordinates $location): null => null, '/places/x');
    }

    public function testUnbindableTypeIsAProgrammingErrorEvenWhenTheParameterIsAbsent(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$name');

        $this->bind('/names/{name?}', static fn (?BoundedString $name = null): null => null, '/names');
    }

    public function testUnbindableTypeIsAProgrammingErrorEvenWhenTheParameterIsNull(): void
    {
        $route = $this->route('/places/{location}', static fn (?GeoCoordinates $location): null => null, '/places/x');
        $route->setParameter('location', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$location');

        $this->dispatch($route);
    }

    public function testUnbindableTypeWithAnExplicitBindingIsLeftToIt(): void
    {
        $this->router->bind('location', static fn (string $value): GeoCoordinates => GeoCoordinates::fromFloats(0, 0));
        $route = $this->bind('/places/{location}', static fn (GeoCoordinates $location): null => null, '/places/x');

        self::assertSame('x', $route->parameter('location'));
    }

    public function testNotFoundMessageUsesTheTranslatedAttributeName(): void
    {
        $loader = new ArrayLoader();
        $loader->addMessages('en', 'validation', ['attributes' => ['email' => 'e-mail address']]);

        try {
            $this->bind('/users/{email}', static fn (EmailAddress $email): null => null, '/users/nope', translator: new Translator($loader, 'en'));
            self::fail('No NotFoundHttpException thrown.');
        } catch (NotFoundHttpException $exception) {
            self::assertSame('The e-mail address route parameter is not a valid email address.', $exception->getMessage());
        }
    }

    public function testNotFoundMessageForACamelCaseRouteParameter(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The user email route parameter is not a valid email address.');

        $this->bind('/users/{userEmail}', static fn (EmailAddress $userEmail): null => null, '/users/nope');
    }

    public function testCompositeWithACustomCodecIsAProgrammingErrorToo(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('$size');

        $this->bind('/sizes/{size}', static fn (Dimensions $size): null => null, '/sizes/x', new ValueObjectMapper([new DimensionsCodec()]));
    }

    /**
     * @param Closure(never, never): null $action
     */
    private function route(string $uri, Closure $action, string $path): Route
    {
        $route = new Route('GET', $uri, ['uses' => $action]);
        $route->bind(Request::create($path));

        return $route;
    }

    /**
     * @param Closure(never, never): null $action
     */
    private function bind(string $uri, Closure $action, string $path, ValueObjectMapper $mapper = new ValueObjectMapper(), ?Translator $translator = null): Route
    {
        $route = $this->route($uri, $action, $path);
        $this->dispatch($route, $mapper, $translator);

        return $route;
    }

    private function dispatch(Route $route, ValueObjectMapper $mapper = new ValueObjectMapper(), ?Translator $translator = null): void
    {
        $binder = new RouteValueObjectBinder($mapper, $this->router, $translator ?? new Translator(new ArrayLoader(), 'en'));
        $binder(new RouteMatched($route, Request::create('/')));
    }
}
