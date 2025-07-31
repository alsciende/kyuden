<?php

declare(strict_types=1);

namespace App\Workflow\ConflictCard;

use App\Entity\CardTypes\ConflictCard;
use App\Enum\StackType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[AsTransitionListener(workflow: 'conflict_card', transition: 'draw_card')]
readonly class DrawCardTransitionListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var ConflictCard $card */
        $card = $event->getSubject();

        $this->logger->debug('Conflict card drawn in hand', [
            'card' => $card->getTitle(),
        ]);

        $card->getPlayer()->getUniqueStack(StackType::Hand)->addCard($card);
    }
}
