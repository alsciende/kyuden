<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Event\DefendersDeclaredEvent;
use App\Exception\Rules\CannotParticipateException;
use App\Message\DeclareDefenders;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\Service\DefendersService;
use App\State\Manager\GameStateManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Defenders are ready characters in play of the defending player (the only active player), not evaded by covert. The
 * conflict action window opens next. Declaring no defender is DeclareNoDefenders.
 */
#[AsMessageHandler]
final readonly class DeclareDefendersHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private ConflictService $conflictService,
        private DefendersService $defendersService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->defendersService->assertCanDeclare($game, $player);
    }

    public function __invoke(DeclareDefenders $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $conflict = $this->gameStateManager->getState($game)->currentConflict();
        $defenderIds = array_values(array_unique($message->getDefenderIds()));
        $defenders = [];
        foreach ($defenderIds as $defenderId) {
            $card = $this->cardRepository->get($defenderId, $game);
            $this->conflictService->assertCanParticipate($card, $player);
            if (in_array($defenderId, $conflict->evadedIds, true)) {
                throw new CannotParticipateException($card, 'it is evaded by covert');
            }
            $defenders[] = $card;
        }

        $this->defendersService->declare($game, $defenderIds, new DefendersDeclaredEvent($game, $player, $defenders));
    }
}
