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
use App\Event\DeckSearchedEvent;
use App\GameLog\GameLogger;
use App\GameLog\LogParam;
use App\View\Visibility;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Looking at cards, revealing them and searching a deck (LookAtCards, LookAtCard, RevealCards, RevealCard,
 * SearchConflictDeck, SearchDynastyDeck): nothing changes in the state,
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
    public function lookAtPile(Player $player, Player $owner, HiddenPile $pile, ?int $quantity, bool $random = false): void
    {
        foreach ($this->stacksOf($owner, $pile, $quantity, $random) as [$stack, $cards]) {
            $this->lookAt($player, $stack, $cards);
        }
    }

    /**
     * The player reveals cards of a hidden pile of the owner: one entry in the log per stack.
     */
    public function revealPile(Player $player, Player $owner, HiddenPile $pile, ?int $quantity, bool $random = false): void
    {
        foreach ($this->stacksOf($owner, $pile, $quantity, $random) as [$stack, $cards]) {
            $this->reveal($player, $stack, $cards);
        }
    }

    /**
     * The top cards of a deck, the top card first, or the cards of a hand: every card unless a quantity is given; the
     * dynasty cards of each province holding some, from the bottom of the province (the quantity does not apply).
     * Random cards of a hand are drawn by the generator of the game, in the order drawn, so that the position of a
     * card in the hand tells nothing.
     *
     * @return list<array{Stack, list<PhysicalCard>}>
     */
    private function stacksOf(Player $owner, HiddenPile $pile, ?int $quantity, bool $random): array
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
        $cards = match (true) {
            $pile !== HiddenPile::Hand => $stack->getTopCards($count),
            $random => array_slice($owner->getGame()->getRandomizer()->shuffleArray($stack->getOrderedCards()), 0, $count),
            default => array_slice($stack->getOrderedCards(), 0, $count),
        };

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
        $this->eventDispatcher->dispatch(new CardsLookedAtEvent($player->getGame(), $player, $this->paramsLookedAt($player, $stack, $cards), $stack));
    }

    /**
     * The player searches a deck of the owner (glossary, Search): they learn every card of it, the top card first, as
     * when they look at it whole; the deck is then to be shuffled, by another action.
     */
    public function searchDeck(Player $player, Player $owner, StackType $deck): void
    {
        $stack = $owner->getUniqueStack($deck);
        $cards = $stack->getTopCards($stack->getCards()->count());
        $this->eventDispatcher->dispatch(new DeckSearchedEvent($player->getGame(), $player, $this->paramsLookedAt($player, $stack, $cards), $stack));
    }

    /**
     * The cards looked at, known to the player and to those who already see them.
     *
     * @param list<PhysicalCard> $cards
     *
     * @return list<LogParam>
     */
    private function paramsLookedAt(Player $player, Stack $stack, array $cards): array
    {
        $game = $player->getGame();

        return array_map(fn (PhysicalCard $card): LogParam => LogParam::card(
            $card,
            $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $viewer === $player || $this->visibility->isStackVisibleTo($stack->getType(), $stack->getPlayer(), $viewer)),
            $this->gameLogger->playersWhere($game, fn (Player $viewer): bool => $viewer === $player || $this->visibility->isCardInStackVisibleTo($card, $stack, $viewer)),
        ), $cards);
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
