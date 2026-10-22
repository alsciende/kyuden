<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\Enum\StackType;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Fills the provinces with the top card of the dynasty deck of their owner, face down.
 * An empty deck is replaced by the shuffled discard pile (DrawDeckService); when the discard pile is empty too, the
 * province stays empty.
 */
readonly class ProvinceRefiller
{
    public function __construct(
        private WorkflowInterface $dynastyCardStateMachine,
        private DrawDeckService $drawDeckService,
    ) {
    }

    public function fill(PhysicalCard $province): void
    {
        $card = $this->drawDeckService->getTopCard($province->getPlayer(), StackType::DynastyDrawDeck);
        if (! $card instanceof DynastyCard) {
            return;
        }

        $this->dynastyCardStateMachine->apply($card, 'draw_card', [
            'stack' => $province->getStack(),
        ]);
    }

    /**
     * Refills a province a dynasty card has left, once no dynasty card remains on it (a holding keeps it from being
     * refilled): "If a player's province is empty, they must immediately refill it".
     *
     * @param Stack $stack the stack the dynasty card has left; nothing happens unless it is a regular province
     */
    public function refillIfEmpty(Stack $stack): void
    {
        if ($stack->getType() !== StackType::RegularProvince) {
            return;
        }

        $province = null;
        foreach ($stack->getCards() as $card) {
            if ($card instanceof DynastyCard) {
                return;
            }

            if ($card instanceof ProvinceCard) {
                $province = $card;
            }
        }

        if ($province instanceof ProvinceCard) {
            $this->fill($province);
        }
    }
}
