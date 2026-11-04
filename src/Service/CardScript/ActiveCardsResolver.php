<?php

declare(strict_types=1);

namespace App\Service\CardScript;

use App\CardScript\AbstractCardScript;
use App\Entity\Game;
use App\Entity\Player;
use App\Model\ActiveCard;
use App\Service\ControlService;
use App\View\Visibility;

/**
 * The active cards of a player, those whose effects they can apply, with their scripts (cards without a script are
 * left out). A player applies the effects of the cards they control: in play, those they control; out of play, those
 * they own (ControlService). Among them, only the cards whose face they know count (Visibility: not their decks nor
 * their facedown dynasty cards), so that what the scripts tell them reveals nothing they do not know.
 */
final readonly class ActiveCardsResolver
{
    public function __construct(
        private CardScriptRegistry $cardScriptRegistry,
        private ControlService $controlService,
        private Visibility $visibility,
    ) {
    }

    /**
     * @return list<ActiveCard>
     */
    public function resolve(Game $game, Player $player): array
    {
        $activeCards = [];
        foreach ($game->getPlayers() as $owner) {
            foreach ($owner->getStacks() as $stack) {
                foreach ($stack->getCards() as $card) {
                    $script = $this->cardScriptRegistry->find($card->getLogicalCard()->getId());
                    if ($script instanceof AbstractCardScript
                        && $this->visibility->isCardInStackVisibleTo($card, $stack, $player)
                        && $this->controlService->getController($card) === $player) {
                        $activeCards[] = new ActiveCard($card, $script);
                    }
                }
            }
        }

        return $activeCards;
    }
}
