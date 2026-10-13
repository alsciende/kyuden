<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DishonorCharacter;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on any character in play: the engine does not apply
 * card effects, which decide who dishonors what and when.
 */
#[AsMessageHandler]
final readonly class DishonorCharacterHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private ConflictService $conflictService,
        private CardStateManager $cardStateManager,
    ) {
    }

    public function __invoke(DishonorCharacter $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->playerRepository->get($message->getPlayerId(), $game);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->conflictService->assertCharacterInPlay($card);

        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->dishonor(),
        );
    }
}
