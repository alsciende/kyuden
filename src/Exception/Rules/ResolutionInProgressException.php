<?php

declare(strict_types=1);

namespace App\Exception\Rules;

/**
 * A player who resolves what they have played (an action, an interrupt or a reaction) ends it (EndResolution): they
 * cannot pass meanwhile.
 */
class ResolutionInProgressException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player is resolving what they have played: they end it before passing.');
    }
}
