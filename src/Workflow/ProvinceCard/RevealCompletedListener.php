<?php

declare(strict_types=1);

namespace App\Workflow\ProvinceCard;

use App\Entity\CardTypes\ProvinceCard;
use App\Event\ProvinceRevealedEvent;
use App\Service\ControlService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Once a province is face up, whatever revealed it, the cards reacting to it may be triggered (ProvinceRevealedEvent).
 */
#[AsCompletedListener(workflow: 'province_card', transition: 'reveal')]
readonly class RevealCompletedListener
{
    public function __construct(
        private ControlService $controlService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param CompletedEvent<ProvinceCard> $event
     */
    public function __invoke(CompletedEvent $event): void
    {
        $province = $event->getSubject();
        $this->eventDispatcher->dispatch(new ProvinceRevealedEvent($province->getGame(), $province, $this->controlService->getController($province)));
    }
}
