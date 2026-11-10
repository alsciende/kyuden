<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\AttackedProvinceAddedEvent;
use App\Exception\Rules\NoConflictException;
use App\Message\AddAttackedProvince;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\AttackedProvinceService;
use App\State\Manager\GameStateManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place during a conflict, from any player of the game: the engine does not apply card effects.
 * The contested ring stays at the province the conflict was declared against; each attacked province has its own
 * strength and is broken separately (3.2.5).
 */
#[AsMessageHandler]
final readonly class AddAttackedProvinceHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private AttackedProvinceService $attackedProvinceService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // during a conflict, from any player of the game
        if ($this->gameStateManager->getState($game)->conflict === null) {
            throw new NoConflictException();
        }
    }

    public function __invoke(AddAttackedProvince $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $state = $this->gameStateManager->getState($game);
        $conflict = $state->currentConflict();
        // a facedown province stays so: a player reveals it (RevealProvince) before anybody passes or ends what they
        // resolve
        $province = $this->attackedProvinceService->assertEligible($this->cardRepository->get($message->getProvinceId(), $game), $conflict);
        $conflict->attackedProvinceIds[] = $province->getId();

        $this->eventDispatcher->dispatch(new AttackedProvinceAddedEvent($game, $player, $province));
        $this->attackedProvinceService->recordStrength($game, $conflict, $province);
        $this->gameStateManager->setState($game, $state);
    }
}
