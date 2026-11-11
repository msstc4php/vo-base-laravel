<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MSSTC4PHP\ValueObject\Laravel\Casts\AsValueObject;
use Override;

final class MisconfiguredCustomer extends Model
{
    protected $table = Customer::TABLE;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email' => AsValueObject::class . ':' . UnmappedValueObject::class,
        ];
    }
}
