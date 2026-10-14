<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\DuelStepException;
use App\Exception\Rules\NotInDuelException;
use App\Message\EndDuel;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The result of the duel is known and its consequences have been applied with other messages (D.8): the duel ends
 * (D.9), and the players can act again in the action window.
 */
#[AsMessageHandler]
final readonly class EndDuelHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
    ) {
    }

    public function __invoke(EndDuel $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);

        $this->gameStateManager->withState($game, function (GameState $state) use ($player): GameState {
            $duel = $state->duel;
            if ($duel === null || ! $duel->resolved) {
                throw new DuelStepException('result');
            }

            if (! $duel->involves($player->getId())) {
                throw new NotInDuelException();
            }

            $state->duel = null;

            return $state;
        });
    }
}
