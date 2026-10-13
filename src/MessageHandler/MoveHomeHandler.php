<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotParticipatingException;
use App\Message\MoveHome;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place during a conflict, from any player of the game: the engine does not apply card effects,
 * which decide who moves what.
 */
#[AsMessageHandler]
final readonly class MoveHomeHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
    ) {
    }

    public function __invoke(MoveHome $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->playerRepository->get($message->getPlayerId(), $game);
        $card = $this->cardRepository->get($message->getCardId(), $game);

        $this->gameStateManager->withState($game, function (GameState $state) use ($card): GameState {
            $conflict = $state->conflict ?? throw new NoConflictException();
            if (! in_array($card->getId(), [...$conflict->attackerIds, ...$conflict->defenderIds], true)) {
                throw new NotParticipatingException($card);
            }

            $conflict->attackerIds = array_values(array_diff($conflict->attackerIds, [$card->getId()]));
            $conflict->defenderIds = array_values(array_diff($conflict->defenderIds, [$card->getId()]));

            return $state;
        });
    }
}
