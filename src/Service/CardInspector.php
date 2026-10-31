<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\HiddenPile;
use App\Event\CardsLookedAtEvent;
use App\Event\CardsRevealedEvent;
use App\GameLog\GameLogger;
use App\GameLog\LogParam;
use App\View\Visibility;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Looking at cards and revealing them (LookAtCards, LookAtCard, RevealCards, RevealCard): nothing changes in the state,
 * the log gives the cards to the players who learn them. The client of the sender keeps them in sight until the
 * effect is resolved.
 */
final readonly class CardInspector
{
    public function __construct(
        private GameLogger $gameLogger,
        private Visibility $visibility,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The top cards of a deck, the top card first, or the cards of a hand: every card unless a quantity is given.
     *
     * @return list<PhysicalCard>
     */
    public function cardsOf(Player $owner, HiddenPile $pile, ?int $quantity): array
    {
        $stack = $owner->getUniqueStack($pile->stackType());
        $count = $quantity ?? $stack->getCards()->count();

        return $pile === HiddenPile::Hand
            ? array_slice($stack->getOrderedCards(), 0, $count)
            : $stack->getTopCards($count);
    }

    /**
     * The player learns the cards (their ids and codes), which the others still know only as they did.
     *
     * @param list<PhysicalCard> $cards
     */
    public function lookAt(Player $player, Stack $stack, array $cards): void
    {
        $game = $player->getGame();
        $params = array_map(fn (PhysicalCard $card): LogParam => LogParam::card(
            $card,
            $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $viewer === $player || $this->visibility->isStackVisibleTo($stack->getType(), $stack->getPlayer(), $viewer)),
            $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $viewer === $player || $this->visibility->isCardInStackVisibleTo($card, $stack, $viewer)),
        ), $cards);
        $this->eventDispatcher->dispatch(new CardsLookedAtEvent($game, $player, $params, $stack));
    }

    /**
     * Every player learns the cards.
     *
     * @param list<PhysicalCard> $cards
     */
    public function reveal(Player $player, Stack $stack, array $cards): void
    {
        $params = array_map(fn (PhysicalCard $card): LogParam => LogParam::card($card, null, null), $cards);
        $this->eventDispatcher->dispatch(new CardsRevealedEvent($player->getGame(), $player, $params, $stack));
    }
}
