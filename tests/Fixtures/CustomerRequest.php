<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Http\ValidatedValueObjects;
use MSSTC4PHP\ValueObject\Laravel\Rules\ValidValueObject;

final class CustomerRequest extends FormRequest
{
    use ValidatedValueObjects;

    /**
     * @return array<string, list<string|ValidValueObject>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', new ValidValueObject(EmailAddress::class)],
            'location' => ['nullable', new ValidValueObject(GeoCoordinates::class)],
        ];
    }
}
