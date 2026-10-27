<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whom an invitation to a friendly game is for: the bot of the host (a program playing through the MCP server, with
 * the token of the invitation), or a friend (anyone using the code of the invitation with their own session).
 */
enum InvitationKind: string
{
    case Bot = 'bot';
    case Friend = 'friend';
}
