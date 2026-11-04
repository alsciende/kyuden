<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\CardScript\ActiveCardsResolver;
use App\Entity\Game;
use App\Entity\Player;

/**
 * The special actions (AppAction::$special) the web client offers a player: those the scripts of their active cards
 * need (AbstractCardScript::getSpecialActions(), ActiveCardsResolver). A reader who is not a player (null) has no
 * active card.
 *
 * The engine accepts a special action whatever the cards: the list only spares the client the actions nobody needs.
 */
final readonly class SpecialActionsResolver
{
    public function __construct(
        private ActionMessageRegistry $actionMessageRegistry,
        private ActiveCardsResolver $activeCardsResolver,
    ) {
    }

    /**
     * @return array<string, list<string>> every special action, by type (sorted), with the ids of the active cards that
     *                                     need it (sorted): none when no active card needs it
     */
    public function resolve(Game $game, ?Player $player): array
    {
        $cardIds = array_fill_keys($this->actionMessageRegistry->getSpecialMessageTypes(), []);
        if (! $player instanceof Player) {
            return $cardIds;
        }

        foreach ($this->activeCardsResolver->resolve($game, $player) as $activeCard) {
            foreach ($activeCard->script->getSpecialActions($activeCard->card, $game) as $className) {
                $type = $this->actionMessageRegistry->getMessageType($className);
                if (! array_key_exists($type, $cardIds)) {
                    throw new \LogicException(\sprintf('The script of %s needs %s, which is not a special action.', $activeCard->card->getLogicalCard()->getId(), $className));
                }

                if (! in_array($activeCard->card->getId(), $cardIds[$type], true)) {
                    $cardIds[$type][] = $activeCard->card->getId();
                }
            }
        }

        return array_map(function (array $ids): array {
            sort($ids);

            return $ids;
        }, $cardIds);
    }
}
