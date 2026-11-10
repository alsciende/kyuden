<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ConflictMovedEvent;
use App\Exception\Rules\NoConflictException;
use App\Message\MoveConflict;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\AttackedProvinceService;
use App\State\Manager\GameStateManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place during a conflict, from any player of the game: the engine does not apply card effects,
 * which decide who moves the conflict. The province the contested ring leaves is no longer attacked; its strength,
 * once recorded, gives way to that of the new province, which the defending player may declare again.
 */
#[AsMessageHandler]
final readonly class MoveConflictHandler implements PlayerActionHandlerInterface
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

    public function __invoke(MoveConflict $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $state = $this->gameStateManager->getState($game);
        $conflict = $state->currentConflict();
        // a facedown province stays so: a player reveals it (RevealProvince) before anybody passes or ends what they
        // resolve
        $province = $this->attackedProvinceService->assertEligible($this->cardRepository->get($message->getProvinceId(), $game), $conflict);
        $fromId = $conflict->provinceId;
        $conflict->attackedProvinceIds = array_map(
            fn (string $id): string => $id === $fromId ? $province->getId() : $id,
            $conflict->attackedProvinceIds,
        );
        $conflict->provinceId = $province->getId();

        $this->eventDispatcher->dispatch(new ConflictMovedEvent($game, $player, $this->cardRepository->get($fromId, $game), $province));
        // before the strength of the province left is forgotten: it tells whether the strengths are recorded
        $this->attackedProvinceService->recordStrength($game, $conflict, $province);
        unset($conflict->provinceStrengths[$fromId]);
        $conflict->declaredStrengthProvinceIds = array_values(array_diff($conflict->declaredStrengthProvinceIds, [$fromId]));
        $this->gameStateManager->setState($game, $state);
    }
}
