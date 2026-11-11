<?php

declare(strict_types=1);

namespace MSSTC4PHP\ValueObject\Laravel\Tests\Fixtures;

/**
 * The core ships no int-backed enum.
 */
enum Priority: int
{
    case Low = 1;
    case High = 9;
}
