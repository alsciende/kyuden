<?php

declare(strict_types=1);

namespace App\Workflow\DynastyCard;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use Psr\Log\LoggerInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

#[AsTransitionListener(workflow: 'dynasty_card', transition: 'draw_card')]
readonly class DrawCardTransitionListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var DynastyCard $card */
        $card = $event->getSubject();

        $province = $event->getContext()['province'];

        if (! $province instanceof ProvinceCard) {
            throw new \LogicException('Province must be provided in the context for drawing a dynasty card.');
        }

        $this->logger->debug('Dynasty card played on Province', [
            'card' => $card->getTitle(),
            'province' => $province->getTitle(),
        ]);

        $province->getStack()->addCard($card);
    }
}
