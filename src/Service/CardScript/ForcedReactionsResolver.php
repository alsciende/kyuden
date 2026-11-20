<?php

declare(strict_types=1);

namespace App\Service\CardScript;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Model\PendingForcedReaction;
use App\Service\AbilityService;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ReactionWindow;

/**
 * The forced reactions a player has to trigger: the cards offered forced in their reaction windows
 * (ReactionWindow::$forcedCardIds) that they can trigger now (AbilityService::canTrigger(), what the engine accepts),
 * so that a card gone elsewhere since, or whose control has changed, blocks nobody. The web client moves the game on
 * for neither player while one is pending; the engine enforces nothing, and waits for its controller
 * (AwaitedPlayersResolver). Read from the state only.
 */
final readonly class ForcedReactionsResolver
{
    public function __construct(
        private PlayerStateManager $playerStateManager,
        private AbilityService $abilityService,
    ) {
    }

    /**
     * @return list<PendingForcedReaction> in the order of their windows, the oldest first
     */
    public function pending(Player $player): array
    {
        $windows = array_filter(
            $this->playerStateManager->getState($player)->getReactionWindows(),
            fn (ReactionWindow $window): bool => $window->forcedCardIds !== [],
        );
        if ($windows === []) {
            return [];
        }

        // the cards of the stacks, loaded with the game
        $cards = [];
        foreach ($player->getGame()->getPlayers() as $owner) {
            foreach ($owner->getStacks() as $stack) {
                foreach ($stack->getCards() as $card) {
                    $cards[$card->getId()] = $card;
                }
            }
        }

        $pending = [];
        foreach ($windows as $window) {
            foreach ($window->forcedCardIds as $cardId) {
                $card = $cards[$cardId] ?? null;
                if ($card instanceof PhysicalCard && $this->abilityService->canTrigger($card, $player)) {
                    $pending[] = new PendingForcedReaction($card, $window);
                }
            }
        }

        return $pending;
    }
}
