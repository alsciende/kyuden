<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * An invitation to a friendly game cannot be used: unknown, expired, used, cancelled, or not for this user.
 */
class InvalidInvitationException extends \RuntimeException
{
}
