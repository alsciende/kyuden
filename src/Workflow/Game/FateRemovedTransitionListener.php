<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\FateRemovedFromCardEvent;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 4.3: 1 fate is removed from each character in play.
 */
#[AsTransitionListener(workflow: 'game', transition: 'fate_removed')]
readonly class FateRemovedTransitionListener
{
    public const int FATE_REMOVED = 1;

    public function __construct(
        private CardStateManager $cardStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            foreach ($player->getCharactersInPlay() as $character) {
                $removed = min(self::FATE_REMOVED, $this->cardStateManager->getState($character)->getFate());
                if ($removed === 0) {
                    continue;
                }

                $this->cardStateManager->withState(
                    $character,
                    fn (CardState $state): CardState => $state->removeFate($removed),
                );
                $this->eventDispatcher->dispatch(new FateRemovedFromCardEvent($game, $character, $removed));
            }
        }
    }
}
