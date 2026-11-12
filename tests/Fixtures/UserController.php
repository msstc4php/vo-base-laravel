<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

use MSSTC4PHP\ValueObject\Identity\EmailAddress;

final class UserController
{
    public function show(EmailAddress $email): string
    {
        return $email->value;
    }
}
