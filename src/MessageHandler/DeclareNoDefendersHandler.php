<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\NoDefendersDeclaredEvent;
use App\Message\DeclareNoDefenders;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\DefendersService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The defending player (the only active player) declares no defender; the conflict action window opens next.
 */
#[AsMessageHandler]
final readonly class DeclareNoDefendersHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private DefendersService $defendersService,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->defendersService->assertCanDeclare($game, $player);
    }

    public function __invoke(DeclareNoDefenders $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->defendersService->declare($game, [], new NoDefendersDeclaredEvent($game, $player));
    }
}
