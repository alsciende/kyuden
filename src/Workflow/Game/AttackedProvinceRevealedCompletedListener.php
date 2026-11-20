<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Repository\CardRepository;
use App\State\Manager\GameStateManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Once the attacked province is revealed, the declaration of the conflict is complete: the cards reacting to it may be
 * triggered (ConflictDeclarationCompletedEvent).
 */
#[AsCompletedListener(workflow: 'game', transition: 'attacked_province_revealed')]
readonly class AttackedProvinceRevealedCompletedListener
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private CardRepository $cardRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param CompletedEvent<Game> $event
     */
    public function __invoke(CompletedEvent $event): void
    {
        $game = $event->getSubject();
        $conflict = $this->gameStateManager->getState($game)->currentConflict();
        $province = $this->cardRepository->get($conflict->provinceId, $game);
        if (! $province instanceof ProvinceCard) {
            throw new \LogicException(\sprintf('The attacked card %s is not a province.', $province->getId()));
        }

        $this->eventDispatcher->dispatch(new ConflictDeclarationCompletedEvent(
            $game,
            $game->getPlayer($conflict->attackingPlayerId),
            $game->getPlayer($conflict->defendingPlayerId),
            $conflict->ring,
            $conflict->type,
            $province,
            array_map(fn (string $id): PhysicalCard => $this->cardRepository->get($id, $game), $conflict->attackerIds),
        ));
    }
}
