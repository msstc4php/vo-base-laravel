<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MSSTC4PHP\ValueObject\Geo\GeoCoordinates;
use MSSTC4PHP\ValueObject\I18n\CountryCode;
use MSSTC4PHP\ValueObject\Identity\EmailAddress;
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use MSSTC4PHP\ValueObject\Network\Port;
use MSSTC4PHP\ValueObject\Primitive\BoundedString;
use MSSTC4PHP\ValueObject\Primitive\IntRange;
use MSSTC4PHP\ValueObject\Primitive\Percentage;
use Override;

/**
 * `email` uses the plain string definition, the rest `AsValueObject::of()`; both
 * end up as the same string.
 */
final class Customer extends Model
{
    public const string TABLE = 'customers';

    public $timestamps = false;

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email' => AsValueObject::class . ':' . EmailAddress::class,
            'port' => AsValueObject::of(Port::class),
            'discount' => AsValueObject::of(Percentage::class),
            'country' => AsValueObject::of(CountryCode::class),
            'priority' => AsValueObject::of(Priority::class),
            'location' => AsValueObject::of(GeoCoordinates::class),
            'quantity' => AsValueObject::of(IntRange::class),
            'label' => AsValueObject::of(BoundedString::class),
        ];
    }
}
