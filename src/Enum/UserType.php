<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who uses an account: a person, or a program (an AI agent) under the responsibility of a person.
 */
enum UserType: string
{
    case Human = 'human';
    case Bot = 'bot';
}
