<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The user plays a game that has not ended: they finish it (or concede it, or end it once abandoned) before starting
 * another one, in the queue or against the house bot.
 */
class GameInProgressException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The user plays a game that has not ended: finish it before starting another one.');
    }
}
