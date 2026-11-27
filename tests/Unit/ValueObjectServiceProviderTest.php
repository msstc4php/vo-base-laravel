<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use LogicException;
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use MSSTC4PHP\ValueObject\Laravel\Casts\CasterCache;
use MSSTC4PHP\ValueObject\Laravel\Http\RequestMacros;
use MSSTC4PHP\ValueObject\Laravel\Http\ValueObjectInput;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Laravel\Internal\DefaultMapper;
use MSSTC4PHP\ValueObject\Laravel\Routing\RouteValueObjectBinder;
use MSSTC4PHP\ValueObject\Laravel\Rules\ValidValueObject;
use MSSTC4PHP\ValueObject\Laravel\Tests\ApplicationTestCase;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\AppCodecs;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\DimensionsCodec;
use MSSTC4PHP\ValueObject\Laravel\ValueObjectServiceProvider;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ValueObjectServiceProvider::class)]
#[UsesClass(RequestMacros::class)]
#[UsesClass(AsValueObject::class)]
#[UsesClass(CasterCache::class)]
#[UsesClass(DefaultMapper::class)]
#[UsesClass(CompositeJson::class)]
#[UsesClass(ValidValueObject::class)]
#[UsesClass(ValueObjectInput::class)]
#[UsesClass(RouteValueObjectBinder::class)]
final class ValueObjectServiceProviderTest extends ApplicationTestCase
{
    public function testMapperIsASingleton(): void
    {
        $app = $this->application();

        self::assertSame($app->make(ValueObjectMapper::class), $app->make(ValueObjectMapper::class));
        self::assertFalse($app->make(ValueObjectMapper::class)->supports(Dimensions::class));
    }

    public function testApplicationMayRebindTheMapperWithCodecs(): void
    {
        $app = $this->application();
        $app->singleton(ValueObjectMapper::class, static fn (): ValueObjectMapper => new ValueObjectMapper([new DimensionsCodec()]));

        self::assertTrue($app->make(ValueObjectMapper::class)->supports(Dimensions::class));
    }

    public function testAMapperBuiltFromACodecSetReachesEveryPart(): void
    {
        $app = $this->application();
        $app->singleton(ValueObjectMapper::class, static fn (): ValueObjectMapper => new ValueObjectMapper(AppCodecs::all()));

        $size = new Dimensions(2, 3);

        self::assertSame('{"width":2,"height":3}', AsValueObject::castUsing([Dimensions::class])->set(new class extends Model {}, 'size', $size, []));
        self::assertFalse($app->make(Factory::class)->make(['size' => '{"width":2,"height":3}'], ['size' => [new ValidValueObject(Dimensions::class)]])->fails());
        self::assertEquals($size, Request::create('/', 'GET', ['size' => '{"width":2,"height":3}'])->__call('valueObject', ['size', Dimensions::class]));

        $route = new Route('GET', '/sizes/{size}', ['uses' => static fn (Dimensions $size): null => null]);
        $route->bind(Request::create('/sizes/2x3'));

        // A composite argument type is refused only by a binder whose mapper knows the codec.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('typed ' . Dimensions::class . ', a composite value object');

        $app->make(RouteValueObjectBinder::class)(new RouteMatched($route, Request::create('/sizes/2x3')));
    }

    public function testMapperBoundBeforeTheProviderIsKept(): void
    {
        $app = $this->application();
        $mapper = new ValueObjectMapper([new DimensionsCodec()]);
        $app->instance(ValueObjectMapper::class, $mapper);

        $app->register(ValueObjectServiceProvider::class, force: true);

        self::assertSame($mapper, $app->make(ValueObjectMapper::class));
    }

    public function testBootRegistersTheRequestMacro(): void
    {
        Request::flushMacros();

        $this->application()->register(ValueObjectServiceProvider::class, force: true);

        self::assertTrue(Request::hasMacro('valueObject'));
    }

    public function testBootListensToMatchedRoutes(): void
    {
        $listeners = $this->application()->make(Dispatcher::class)->getRawListeners()[RouteMatched::class] ?? [];

        self::assertIsArray($listeners);
        self::assertContains(RouteValueObjectBinder::class, $listeners);
    }
}
