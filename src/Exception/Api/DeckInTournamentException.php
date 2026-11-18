<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The deck is registered in a tournament still to be played: it cannot be deleted until the tournament ends.
 */
class DeckInTournamentException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This deck is registered in a tournament still to be played: change the deck of your registration or unregister first.');
    }
}
