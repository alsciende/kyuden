<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

#[AsEnterListener(workflow: 'game', place: 'reveal_facedown_dynasty_cards')]
readonly class RevealFacedownDynastyCardsEnterListener
{
    public function __construct(
    ) {
    }

    public function __invoke(EnterEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();
    }
}
