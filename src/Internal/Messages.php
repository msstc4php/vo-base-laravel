<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Internal;

/**
 * @internal one wording for every input path; public as `ValidValueObject::DEFAULT_MESSAGE`
 */
final class Messages
{
    public const string INVALID = 'The :attribute field is not a valid :type.';

    private function __construct()
    {
    }
}
