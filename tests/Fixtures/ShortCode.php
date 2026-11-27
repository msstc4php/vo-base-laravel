<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use Override;

final class ShortCode extends Code
{
    #[Override]
    protected function pattern(): string
    {
        return '/^[a-z]{1,3}$/D';
    }
}
