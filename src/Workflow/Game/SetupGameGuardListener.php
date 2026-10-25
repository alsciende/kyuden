<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Entity\Player;
use App\State\Manager\PlayerStateManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'setup_game')]
readonly class SetupGameGuardListener
{
    public function __construct(
        private PlayerStateManager $stateManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param GuardEvent<Game> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            if ($this->hasPlayerSelectedStrongholdProvince($player) === false) {
                $event->setBlocked(true, 'Player has not selected stronghold province.');
                $this->logger->debug('SetupGameGuardListener', [
                    'is_blocked' => true,
                    'player_id' => $player->getId(),
                ]);

                return;
            }
        }
    }

    private function hasPlayerSelectedStrongholdProvince(Player $player): bool
    {
        return is_string($this->stateManager->getState($player)->getStrongholdProvinceId());
    }
}
