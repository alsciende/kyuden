<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How an access token was issued: a session opened by a person who logged in, or an API key given to a bot.
 */
enum AccessTokenType: string
{
    case Session = 'session';
    case ApiKey = 'api_key';
}
