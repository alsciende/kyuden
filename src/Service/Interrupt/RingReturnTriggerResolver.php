<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Enum\Element;
use App\Exception\Data\DataException;
use App\Message\ActionInterface;
use App\Message\ReturnRing;
use App\State\Manager\GameStateManager;
use App\Trigger\RingReturningTrigger;

/**
 * A claimed ring would be returned to the unclaimed ring pool (RingReturningTrigger): by an effect (ReturnRing), or
 * every claimed ring in the fate phase (`rings_returned`).
 */
final readonly class RingReturnTriggerResolver implements ImminentTriggerResolverInterface
{
    public function __construct(
        private GameStateManager $gameStateManager,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        if (! $message instanceof ReturnRing) {
            return [];
        }

        try {
            $game->getPlayer($message->getPlayerId());
        } catch (DataException) {
            // refused by its handler: nothing would occur
            return [];
        }

        return $this->returning($game, [$message->getRing()]);
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        return $transition === 'rings_returned' ? $this->returning($game, Element::cases()) : [];
    }

    /**
     * @param list<Element> $rings
     *
     * @return list<RingReturningTrigger>
     */
    private function returning(Game $game, array $rings): array
    {
        $state = $this->gameStateManager->getState($game);
        $triggers = [];
        foreach ($rings as $ring) {
            $claimedById = $state->getRing($ring)->claimedById;
            if ($claimedById !== null) {
                $triggers[] = new RingReturningTrigger($ring, $game->getPlayer($claimedById));
            }
        }

        return $triggers;
    }
}
