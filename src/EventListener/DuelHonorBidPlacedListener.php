<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Player;
use App\Event\HonorBidPlacedEvent;
use App\Event\HonorBidRevealedEvent;
use App\Exception\Rules\NoHonorBidException;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * During a duel, the players involved reveal their honor dials simultaneously once both have bid (D.4): the secret
 * bids become the honor bids.
 */
#[AsEventListener]
readonly class DuelHonorBidPlacedListener
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(HonorBidPlacedEvent $event): void
    {
        $game = $event->getGame();
        $duel = $this->gameStateManager->getState($game)->duel;
        if ($duel === null || ! $duel->atBidStep()) {
            return;
        }

        $duelists = array_map(fn (string $playerId): Player => $game->getPlayer($playerId), $duel->playerIds());
        foreach ($duelists as $duelist) {
            if ($this->playerStateManager->getState($duelist)->getPendingHonorBid() === null) {
                return;
            }
        }

        foreach ($duelists as $duelist) {
            $this->playerStateManager->withState(
                $duelist,
                fn (PlayerState $state): PlayerState => $state->revealHonorBid(),
            );
            $this->eventDispatcher->dispatch(new HonorBidRevealedEvent($game, $duelist, $this->playerStateManager->getState($duelist)->getHonorBid() ?? throw new NoHonorBidException()));
        }
        $this->gameStateManager->withState($game, function (GameState $state): GameState {
            $state->currentDuel()->revealed = true;

            return $state;
        });
    }
}
