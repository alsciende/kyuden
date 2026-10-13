<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\AlreadyParticipatingException;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotInConflictException;
use App\Message\MoveToConflict;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place during a conflict, from any player of the game: the engine does not apply card effects,
 * which decide who moves what. The character, bowed or ready, joins the side of its controller.
 */
#[AsMessageHandler]
final readonly class MoveToConflictHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private ConflictService $conflictService,
    ) {
    }

    public function __invoke(MoveToConflict $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->playerRepository->get($message->getPlayerId(), $game);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->conflictService->assertCharacterInPlay($card);

        $this->gameStateManager->withState($game, function (GameState $state) use ($card): GameState {
            $conflict = $state->conflict ?? throw new NoConflictException();
            if (in_array($card->getId(), [...$conflict->attackerIds, ...$conflict->defenderIds], true)) {
                throw new AlreadyParticipatingException($card);
            }

            match ($card->getPlayer()->getId()) {
                $conflict->attackingPlayerId => $conflict->attackerIds[] = $card->getId(),
                $conflict->defendingPlayerId => $conflict->defenderIds[] = $card->getId(),
                default => throw new NotInConflictException(),
            };

            return $state;
        });
    }
}
