<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\RulesException;

/**
 * Handler of a public action of a player (App\Message\PlayerActionInterface). assertAvailable() checks what the action
 * requires from the state whatever its payload (game place, active player, duel step…): the handler calls it before
 * handling the message, and App\Service\AvailableActionsResolver calls it to list the actions a player can send now.
 * The handled message is the type of the parameter of __invoke() (App\Kernel).
 */
interface PlayerActionHandlerInterface
{
    /**
     * Only reads the state.
     *
     * @throws RulesException|WorkflowException when the player cannot send the action in the current state
     */
    public function assertAvailable(Game $game, Player $player): void;
}
