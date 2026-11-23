<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Internal;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use MSSTC4PHP\ValueObject\Laravel\Casts\CasterCache;
use MSSTC4PHP\ValueObject\Laravel\Internal\CompositeJson;
use MSSTC4PHP\ValueObject\Laravel\Internal\DefaultMapper;
use MSSTC4PHP\ValueObject\Laravel\Rules\ValidValueObject;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\DimensionsCodec;
use MSSTC4PHP\ValueObject\Payload\ValueObjectMapper;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(DefaultMapper::class)]
#[UsesClass(AsValueObject::class)]
#[UsesClass(CasterCache::class)]
#[UsesClass(ValidValueObject::class)]
#[UsesClass(CompositeJson::class)]
final class DefaultMapperTest extends TestCase
{
    private ?Container $previous = null;

    #[Override]
    protected function setUp(): void
    {
        $this->previous = Container::getInstance();
        Container::setInstance(new Container());
    }

    #[Override]
    protected function tearDown(): void
    {
        Container::setInstance($this->previous);
    }

    public function testWithoutABindingOnePlainMapperIsShared(): void
    {
        $mapper = DefaultMapper::get();

        self::assertSame($mapper, DefaultMapper::get());
        self::assertFalse($mapper->supports(Dimensions::class));
    }

    public function testTheContainersBindingIsUsed(): void
    {
        $mapper = new ValueObjectMapper([new DimensionsCodec()]);
        Container::getInstance()->instance(ValueObjectMapper::class, $mapper);

        self::assertSame($mapper, DefaultMapper::get());
    }

    public function testABindingToAnythingElseIsAConfigurationError(): void
    {
        Container::getInstance()->instance(ValueObjectMapper::class, new stdClass());

        $this->expectExceptionObject(new LogicException('The container binds ' . ValueObjectMapper::class . ' to stdClass.'));

        DefaultMapper::get();
    }

    public function testACastWithoutAMapperStoresThroughTheBoundCodec(): void
    {
        Container::getInstance()->instance(ValueObjectMapper::class, new ValueObjectMapper([new DimensionsCodec()]));
        $model = new class extends Model {};

        self::assertSame('{"width":2,"height":3}', AsValueObject::castUsing([Dimensions::class])->set($model, 'size', new Dimensions(2, 3), []));
        self::assertSame('{"width":2,"height":3}', new AsValueObject(Dimensions::class)->set($model, 'size', new Dimensions(2, 3), []));
    }

    public function testARuleWithoutAMapperReadsThroughTheBoundCodec(): void
    {
        Container::getInstance()->instance(ValueObjectMapper::class, new ValueObjectMapper([new DimensionsCodec()]));
        $failed = false;

        new ValidValueObject(Dimensions::class)->validate('size', '{"width":2,"height":3}', static function () use (&$failed): never {
            $failed = true;

            throw new LogicException('The rule failed.');
        });

        self::assertFalse($failed);
    }
}
