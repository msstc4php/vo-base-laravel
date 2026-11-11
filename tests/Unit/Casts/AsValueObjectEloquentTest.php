<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Casts;

use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use MSSTC4PHP\ValueObject\Exception\InvalidEmailAddressException;
use MSSTC4PHP\ValueObject\Exception\InvalidPayloadException;
use MSSTC4PHP\ValueObject\Exception\InvalidPortException;
use MSSTC4PHP\ValueObject\Exception\InvalidValueObjectException;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Customer;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Dimensions;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\Priority;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use MSSTC4PHP\ValueObject\Primitive\Percentage;
use MSSTC4PHP\ValueObject\Temporal\DateRange;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AsValueObject::class)]
final class AsValueObjectEloquentTest extends TestCase
{
    private Manager $database;

    #[Override]
    protected function setUp(): void
    {
        $this->database = new Manager();
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();

        $this->database->getConnection()->getSchemaBuilder()->create(Customer::TABLE, static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email')->nullable();
            $table->integer('port')->nullable();
            $table->float('discount')->nullable();
            $table->string('country', 2)->nullable();
            $table->integer('priority')->nullable();
            $table->text('location')->nullable();
            $table->text('quantity')->nullable();
            $table->text('label')->nullable();
            $table->text('period')->nullable();
            $table->text('size')->nullable();
        });
    }

    /**
     * @return iterable<string, array{string, object, string|int|float}>
     */
    public static function valueObjects(): iterable
    {
        yield 'string-backed' => ['email', EmailAddress::from('jane@example.com'), 'jane@example.com'];
        yield 'int-backed' => ['port', Port::from(8080), 8080];
        yield 'float-backed' => ['discount', Percentage::from(12.5), 12.5];
        yield 'string enum' => ['country', CountryCode::DE, 'DE'];
        yield 'int enum' => ['priority', Priority::High, 9];
        yield 'composite' => ['location', GeoCoordinates::fromFloats(52.52, 13.405), '{"lat":52.52,"lng":13.405}'];
        yield 'composite with bounds' => ['label', new BoundedString('abc', 1, 5), '{"value":"abc","minLength":1,"maxLength":5}'];
        yield 'date range' => [
            'period',
            new DateRange(new DateTimeImmutable('2026-11-11T00:00:00+00:00'), new DateTimeImmutable('2026-11-12T12:30:00.250000+02:00')),
            '{"start":"2026-11-11T00:00:00.000000+00:00","end":"2026-11-12T12:30:00.250000+02:00"}',
        ];
        yield 'composite with a custom codec' => ['size', new Dimensions(640, 480), '{"width":640,"height":480}'];
    }

    #[DataProvider('valueObjects')]
    public function testRoundTripsThroughTheDatabase(string $attribute, object $value, string|int|float $stored): void
    {
        $id = $this->create([$attribute => $value]);

        self::assertSame($stored, $this->stored($id, $attribute));
        self::assertEquals($value, $this->find($id)->getAttribute($attribute));
    }

    public function testNullStaysNull(): void
    {
        $id = $this->create(['email' => null, 'location' => null]);

        self::assertNull($this->stored($id, 'email'));
        $customer = $this->find($id);
        self::assertNull($customer->getAttribute('email'));
        self::assertNull($customer->getAttribute('location'));
        self::assertSame(['id' => $id, 'email' => null, 'location' => null], array_intersect_key($customer->toArray(), ['id' => 0, 'email' => 0, 'location' => 0]));
    }

    public function testRawPayloadAssignedToTheAttributeIsValidatedAndStored(): void
    {
        $id = $this->create(['email' => 'jane@example.com', 'port' => '8080', 'quantity' => ['min' => 1, 'max' => 3], 'location' => '{"lat":1,"lng":2}']);

        self::assertSame(8080, $this->stored($id, 'port'));
        self::assertSame('{"min":1,"max":3}', $this->stored($id, 'quantity'));
        self::assertSame('{"lat":1,"lng":2}', $this->stored($id, 'location'));
        $customer = $this->find($id);
        self::assertEquals(EmailAddress::from('jane@example.com'), $customer->getAttribute('email'));
        self::assertEquals(new IntRange(1, 3), $customer->getAttribute('quantity'));
    }

    public function testInvalidRawPayloadIsRejectedOnAssignment(): void
    {
        $this->expectException(InvalidPortException::class);

        new Customer()->setAttribute('port', 70000);
    }

    public function testWrongTypeIsRejectedOnAssignment(): void
    {
        $this->expectException(InvalidPayloadException::class);

        new Customer()->setAttribute('email', Port::from(80));
    }

    public function testCompositeRejectsAStringThatIsNotAJsonObject(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('must be an array, string given');

        new Customer()->setAttribute('location', '"52.52,13.405"');
    }

    public function testCompositeRejectsAStringThatIsNotJson(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('must be JSON text, invalid JSON given');
        $this->expectExceptionCode(\JSON_ERROR_SYNTAX);

        new Customer()->setAttribute('location', '{lat: 1}');
    }

    public function testQueryExpressionIsRejected(): void
    {
        $this->expectException(InvalidPayloadException::class);

        new Customer()->setAttribute('email', new Expression('LOWER(email)'));
    }

    public function testDateRangeWithoutAnOffsetIsRejected(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('ISO 8601 instant with an offset');

        new Customer()->setAttribute('period', ['start' => '2026-11-11T00:00:00', 'end' => '2026-11-12T00:00:00']);
    }

    public function testCustomCodecRejectsAnInvalidStoredValue(): void
    {
        $id = $this->insert(['size' => '{"width":0,"height":480}']);

        $this->expectException(InvalidValueObjectException::class);

        $this->find($id)->getAttribute('size');
    }

    public function testUnencodableCompositeFailsAsInvalidPayload(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('cannot be encoded as JSON');
        $this->expectExceptionCode(\JSON_ERROR_UTF8);

        new Customer()->setAttribute('label', new BoundedString("\xB1\x31"));
    }

    /**
     * @return iterable<string, array{array{value: string, minLength: int, maxLength: int}|string}>
     */
    public static function rawBoundedStringPayloads(): iterable
    {
        yield 'array' => [['value' => 'abc', 'minLength' => 0, 'maxLength' => 100000]];
        yield 'JSON text' => ['{"value":"abc","minLength":0,"maxLength":100000}'];
    }

    /**
     * fill($request->all()) must not let the client choose the bounds it is checked against.
     *
     * @param array{value: string, minLength: int, maxLength: int}|string $payload
     */
    #[DataProvider('rawBoundedStringPayloads')]
    public function testRawPayloadOfATrustedSourceOnlyClassIsRefusedOnAssignment(array|string $payload): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('cannot be read from untrusted input');

        new Customer()->fill(['label' => $payload]);
    }

    public function testStoredPayloadOfATrustedSourceOnlyClassIsRead(): void
    {
        $id = $this->insert(['label' => '{"value":"abc","minLength":1,"maxLength":5}']);

        self::assertEquals(new BoundedString('abc', 1, 5), $this->find($id)->getAttribute('label'));
    }

    public function testInvalidStoredValueFailsLoudly(): void
    {
        $id = $this->insert(['email' => 'not-an-email']);

        $this->expectException(InvalidEmailAddressException::class);

        $this->find($id)->getAttribute('email');
    }

    public function testStoredValueOfTheWrongTypeFailsLoudly(): void
    {
        $id = $this->insert(['port' => 'eighty']);

        $this->expectException(InvalidPayloadException::class);

        $this->find($id)->getAttribute('port');
    }

    public function testNumericStringsFromTheDriverAreRead(): void
    {
        $id = $this->insert(['port' => '443', 'discount' => '7.5', 'priority' => '1']);

        $customer = $this->find($id);
        self::assertEquals(Port::from(443), $customer->getAttribute('port'));
        self::assertEquals(Percentage::from(7.5), $customer->getAttribute('discount'));
        self::assertSame(Priority::Low, $customer->getAttribute('priority'));
    }

    public function testToArrayUsesThePayloadForm(): void
    {
        $id = $this->create(['email' => EmailAddress::from('jane@example.com'), 'location' => GeoCoordinates::fromFloats(1.5, 2.5), 'country' => CountryCode::DE]);

        $array = $this->find($id)->toArray();

        self::assertSame('jane@example.com', $array['email']);
        self::assertSame(['lat' => 1.5, 'lng' => 2.5], $array['location']);
        self::assertSame('DE', $array['country']);
    }

    public function testReformattedJsonIsNotDirty(): void
    {
        $id = $this->insert(['location' => '{ "lng": 2.5, "lat": 1.5 }']);
        $customer = $this->find($id);

        $customer->setAttribute('location', GeoCoordinates::fromFloats(1.5, 2.5));

        self::assertFalse($customer->isDirty('location'));
    }

    public function testStoredTrustedSourceOnlyValueIsComparedAsTrusted(): void
    {
        $id = $this->insert(['label' => '{ "value": "abc", "minLength": 1, "maxLength": 5 }']);
        $customer = $this->find($id);

        $customer->setAttribute('label', new BoundedString('abc', 1, 5));

        self::assertFalse($customer->isDirty('label'));
    }

    public function testChangedValueIsDirty(): void
    {
        $id = $this->create(['location' => GeoCoordinates::fromFloats(1.5, 2.5), 'email' => null]);
        $customer = $this->find($id);

        $customer->setAttribute('location', GeoCoordinates::fromFloats(1.5, 3.5));
        $customer->setAttribute('email', 'jane@example.com');

        self::assertTrue($customer->isDirty('location'));
        self::assertTrue($customer->isDirty('email'));
    }

    public function testInvalidStoredValueCanBeOverwritten(): void
    {
        $id = $this->insert(['email' => 'not-an-email']);
        $customer = $this->find($id);

        $customer->setAttribute('email', EmailAddress::from('jane@example.com'));

        self::assertTrue($customer->isDirty('email'));
        $customer->save();
        self::assertSame('jane@example.com', $this->stored($id, 'email'));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function create(array $attributes): int
    {
        $customer = new Customer();
        $customer->fill($attributes);
        $customer->save();

        return $this->idOf($customer->getKey());
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function insert(array $row): int
    {
        return $this->idOf($this->database->getConnection()->table(Customer::TABLE)->insertGetId($row));
    }

    private function find(int $id): Customer
    {
        return Customer::query()->findOrFail($id);
    }

    private function stored(int $id, string $column): mixed
    {
        return $this->database->getConnection()->table(Customer::TABLE)->where('id', $id)->value($column);
    }

    private function idOf(mixed $key): int
    {
        self::assertIsInt($key);

        return $key;
    }
}
