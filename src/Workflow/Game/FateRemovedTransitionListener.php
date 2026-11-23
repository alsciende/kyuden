<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\FatePhaseFateRemovedEvent;
use App\Event\FateRemovedFromCardEvent;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
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
        private GameStateManager $gameStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        $state = $this->gameStateManager->getState($game);
        // the characters an interrupt has kept their fate (CancelImminentTrigger: Embrace the Void)
        $kept = $state->interruptedTransition?->name === 'fate_removed' ? $state->interruptedTransition->cancelledCardIds : [];
        $total = 0;
        foreach ($game->getPlayers() as $player) {
            foreach ($player->getCharactersInPlay() as $character) {
                if (in_array($character->getId(), $kept, true)) {
                    continue;
                }

                $removed = min(self::FATE_REMOVED, $this->cardStateManager->getState($character)->getFate());
                if ($removed === 0) {
                    continue;
                }

                $this->cardStateManager->withState(
                    $character,
                    fn (CardState $state): CardState => $state->removeFate($removed),
                );
                $this->eventDispatcher->dispatch(new FateRemovedFromCardEvent($game, $character, $removed));
                $total += $removed;
            }
        }

        if ($total > 0) {
            $this->eventDispatcher->dispatch(new FatePhaseFateRemovedEvent($game, $total));
        }
    }
}
