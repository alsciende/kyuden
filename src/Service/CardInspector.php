<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\HiddenPile;
use App\Enum\StackType;
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
     * The player looks at cards of a hidden pile of the owner: one entry in the log per stack.
     */
    public function lookAtPile(Player $player, Player $owner, HiddenPile $pile, ?int $quantity): void
    {
        foreach ($this->stacksOf($owner, $pile, $quantity) as [$stack, $cards]) {
            $this->lookAt($player, $stack, $cards);
        }
    }

    /**
     * The player reveals cards of a hidden pile of the owner: one entry in the log per stack.
     */
    public function revealPile(Player $player, Player $owner, HiddenPile $pile, ?int $quantity): void
    {
        foreach ($this->stacksOf($owner, $pile, $quantity) as [$stack, $cards]) {
            $this->reveal($player, $stack, $cards);
        }
    }

    /**
     * The top cards of a deck, the top card first, or the cards of a hand: every card unless a quantity is given; the
     * dynasty cards of each province holding some, from the bottom of the province (the quantity does not apply).
     *
     * @return list<array{Stack, list<PhysicalCard>}>
     */
    private function stacksOf(Player $owner, HiddenPile $pile, ?int $quantity): array
    {
        if ($pile === HiddenPile::Provinces) {
            $stacks = [];
            foreach ($owner->getStacks(StackType::RegularProvince) as $province) {
                $cards = array_values(array_filter($province->getOrderedCards(), fn (PhysicalCard $card): bool => $card instanceof DynastyCard));
                if ($cards !== []) {
                    $stacks[] = [$province, $cards];
                }
            }

            return $stacks;
        }

        $stack = $owner->getUniqueStack($pile->stackType());
        $count = $quantity ?? $stack->getCards()->count();
        $cards = $pile === HiddenPile::Hand
            ? array_slice($stack->getOrderedCards(), 0, $count)
            : $stack->getTopCards($count);

        // an empty pile still writes its entry: the players read that there was nothing to see
        return [[$stack, $cards]];
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
