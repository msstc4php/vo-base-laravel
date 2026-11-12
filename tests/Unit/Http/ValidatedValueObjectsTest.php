<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Unit\Http;

use Illuminate\Support\Facades\Route;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Http\RequestMacros;
use MSSTC4PHP\ValueObject\Laravel\Http\ValidatedValueObjects;
use MSSTC4PHP\ValueObject\Laravel\Http\ValueObjectInput;
use MSSTC4PHP\ValueObject\Laravel\Internal\TypeLabel;
use MSSTC4PHP\ValueObject\Laravel\Routing\RouteValueObjectBinder;
use MSSTC4PHP\ValueObject\Laravel\Rules\ValidValueObject;
use MSSTC4PHP\ValueObject\Laravel\Tests\ApplicationTestCase;
use MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures\CustomerRequest;
use MSSTC4PHP\ValueObject\Laravel\ValueObjectServiceProvider;
use MSSTC4PHP\ValueObject\Network\Port;
use Override;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversTrait(ValidatedValueObjects::class)]
#[UsesClass(RequestMacros::class)]
#[UsesClass(RouteValueObjectBinder::class)]
#[UsesClass(ValueObjectInput::class)]
#[UsesClass(ValidValueObject::class)]
#[UsesClass(ValueObjectServiceProvider::class)]
#[UsesClass(TypeLabel::class)]
final class ValidatedValueObjectsTest extends ApplicationTestCase
{
    public function testValidatedValuesBecomeValueObjects(): void
    {
        $this->postJson('/customers', ['email' => 'jane@example.com', 'location' => ['lat' => 52.52, 'lng' => 13.405]])
            ->assertOk()
            ->assertExactJson(['email' => 'jane@example.com', 'location' => ['lat' => 52.52, 'lng' => 13.405], 'port' => null])
        ;
    }

    public function testCompositeAsJsonTextFromAForm(): void
    {
        $this->post('/customers', ['email' => 'jane@example.com', 'location' => '{"lat":52.52,"lng":13.405}'])
            ->assertOk()
            ->assertJsonPath('location', ['lat' => 52.52, 'lng' => 13.405])
        ;
    }

    public function testOptionalFieldAbsentIsNull(): void
    {
        $this->postJson('/customers', ['email' => 'jane@example.com'])
            ->assertOk()
            ->assertJsonPath('location', null)
        ;
    }

    public function testKeyOutsideTheRulesIsNullEvenWhenSent(): void
    {
        $this->postJson('/customers', ['email' => 'jane@example.com', 'port' => '8080'])
            ->assertOk()
            ->assertJsonPath('port', null)
        ;
    }

    public function testInvalidInputIsRejectedByTheRuleBeforeTheController(): void
    {
        $response = $this->postJson('/customers', ['email' => 'nope']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => 'The email field is not a valid email address.']);
        self::assertStringNotContainsString('MSSTC4PHP', (string) $response->baseResponse->getContent());
    }

    #[Override]
    protected function defineRoutes($router): void
    {
        Route::post('/customers', static fn (CustomerRequest $request): array => [
            'email' => $request->valueObject('email', EmailAddress::class)?->value,
            'location' => $request->valueObject('location', GeoCoordinates::class)?->jsonSerialize(),
            'port' => $request->valueObject('port', Port::class)?->value,
        ]);
    }
}
