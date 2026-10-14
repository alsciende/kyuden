<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Message\HonorCharacter;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on any character in play: the engine does not apply
 * card effects, which decide who honors what and when.
 */
#[AsMessageHandler]
final readonly class HonorCharacterHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private ConflictService $conflictService,
        private CardStateManager $cardStateManager,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(HonorCharacter $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->conflictService->assertCharacterInPlay($card);

        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->honor(),
        );
    }
}
