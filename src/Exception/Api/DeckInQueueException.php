<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The user waits in the matchmaking queue with the deck: it cannot be deleted until they leave.
 */
class DeckInQueueException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('You wait in the queue with this deck: leave the queue first.');
    }
}
