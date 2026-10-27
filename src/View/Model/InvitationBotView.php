<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * The bot of the host of an invitation: the user it plays as.
 */
final readonly class InvitationBotView
{
    public function __construct(
        public string $userId,
        public string $name,
    ) {
    }
}
