<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Enum\Orientation;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Registry;

/**
 * 4.5: each bowed card in play is readied (characters, attachments, strongholds).
 */
#[AsTransitionListener(workflow: 'game', transition: 'cards_readied')]
readonly class CardsReadiedTransitionListener
{
    public function __construct(
        private Registry $workflowRegistry,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($game->getPhysicalCards() as $card) {
            if ($card->getOrientation() === Orientation::Sideways && $this->workflowRegistry->get($card)->can($card, 'ready')) {
                $this->workflowRegistry->get($card)->apply($card, 'ready');
            }
        }
    }
}
