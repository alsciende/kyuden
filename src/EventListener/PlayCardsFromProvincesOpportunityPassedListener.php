<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\OpportunityPassedEvent;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * During the play cards from provinces step, the first player to pass gains 1 fate.
 */
#[AsEventListener]
readonly class PlayCardsFromProvincesOpportunityPassedListener
{
    public const int FIRST_TO_PASS_FATE = 1;

    public function __construct(
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(OpportunityPassedEvent $event): void
    {
        if ($event->getGame()->getCurrentPlace() !== 'play_cards_from_provinces' || ! $event->isFirstToPass()) {
            return;
        }

        $this->stateManager->withState(
            $event->getPlayer(),
            fn (PlayerState $state): PlayerState => $state->incrementFate(self::FIRST_TO_PASS_FATE),
        );
    }
}
