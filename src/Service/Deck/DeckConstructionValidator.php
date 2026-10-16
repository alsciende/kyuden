<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Entity\LogicalCard;
use App\Enum\Clan;
use App\Enum\Element;
use App\Enum\Role;
use App\Enum\Side;
use App\Repository\LogicalCardRepository;

/**
 * The deckbuilding rules of the Stronghold format (glossary, "Deckbuilding"): 1 stronghold, at most 1 role, 5
 * provinces (one per element, in-clan or neutral, different titles), dynasty and conflict decks of 40 to 45 cards
 * (dynasty cards in-clan or neutral, conflict cards in-clan, neutral, or bought with influence from a single other
 * clan), at most 3 copies by title (or the deck limit of the card), and the role restrictions.
 *
 * Roles change these rules: a Keeper role adds 3 influence, a Seeker role allows to replace a province with an
 * additional province of its element, a Support role adds 8 influence that can only be spent on the cards of its clan.
 * The lists of banned and restricted cards (Imperial Law) are not checked.
 */
readonly class DeckConstructionValidator
{
    public const int MIN_DECK_SIZE = 40;
    public const int MAX_DECK_SIZE = 45;
    public const int PROVINCES = 5;
    public const int DEFAULT_DECK_LIMIT = 3;
    public const int KEEPER_INFLUENCE = 3;
    public const int SUPPORT_INFLUENCE = 8;

    public function __construct(
        private LogicalCardRepository $logicalCardRepository,
    ) {
    }

    /**
     * @param array<string, int> $cards quantity by card code, every code naming a card
     *
     * @return list<string> the rules the deck breaks
     */
    public function validate(array $cards): array
    {
        $logicalCards = [];
        foreach ($this->logicalCardRepository->findBy(['id' => array_map(strval(...), array_keys($cards))]) as $logicalCard) {
            $logicalCards[$logicalCard->getId()] = $logicalCard;
        }

        /** @var array<string, list<array{LogicalCard, int}>> $bySide */
        $bySide = [];
        foreach ($cards as $code => $quantity) {
            $logicalCard = $logicalCards[(string) $code] ?? throw new \InvalidArgumentException("Unknown card {$code}.");
            $bySide[$logicalCard->getSide()->value][] = [$logicalCard, $quantity];
        }
        /** @return list<array{LogicalCard, int}> */
        $cardsOf = fn (Side ...$sides): array => array_merge(...array_map(fn (Side $side): array => $bySide[$side->value] ?? [], $sides));
        $count = fn (Side ...$sides): int => array_sum(array_column($cardsOf(...$sides), 1));

        $errors = [];

        if ($count(Side::Stronghold) !== 1) {
            $errors[] = 'The deck must have exactly 1 stronghold.';
        }
        if ($count(Side::Role) > 1) {
            $errors[] = 'The deck can have at most 1 role.';
        }
        foreach ([Side::Dynasty, Side::Conflict] as $side) {
            $deckSize = $count($side);
            if ($deckSize < self::MIN_DECK_SIZE || $deckSize > self::MAX_DECK_SIZE) {
                $errors[] = sprintf('The %s deck must have %d to %d cards, it has %d.', $side->value, self::MIN_DECK_SIZE, self::MAX_DECK_SIZE, $deckSize);
            }
        }

        $roleTraits = $count(Side::Role) === 1 ? $cardsOf(Side::Role)[0][0]->getTraits() ?? [] : [];
        $errors = [...$errors, ...$this->validateCopies($cardsOf(Side::Dynasty, Side::Conflict))];
        $errors = [...$errors, ...$this->validateRoleRestrictions($cardsOf(Side::Dynasty, Side::Conflict, Side::Province), $roleTraits)];
        $errors = [...$errors, ...$this->validateProvinces($cardsOf(Side::Province), $roleTraits)];
        if ($count(Side::Stronghold) === 1) {
            $errors = [...$errors, ...$this->validateClans($cardsOf(Side::Stronghold)[0][0], $roleTraits, $cardsOf(Side::Dynasty, Side::Conflict, Side::Province))];
        }

        return $errors;
    }

    /**
     * At most 3 copies of a card by title in the dynasty and conflict decks, or the limit of the card ("Limit X per
     * deck").
     *
     * @param list<array{LogicalCard, int}> $cards
     *
     * @return list<string>
     */
    private function validateCopies(array $cards): array
    {
        $copies = [];
        $limits = [];
        foreach ($cards as [$card, $quantity]) {
            $title = $card->getName();
            $copies[$title] = ($copies[$title] ?? 0) + $quantity;
            $limits[$title] = min($limits[$title] ?? PHP_INT_MAX, $card->getDeckLimit() ?? self::DEFAULT_DECK_LIMIT);
        }

        $errors = [];
        foreach ($copies as $title => $copiesOfTitle) {
            if ($copiesOfTitle > $limits[$title]) {
                $errors[] = sprintf('The deck can have at most %d copies of %s, it has %d.', $limits[$title], $title, $copiesOfTitle);
            }
        }

        return $errors;
    }

    /**
     * A card restricted to a role ("Keeper role only", "Air role only") requires a role with that trait.
     *
     * @param list<array{LogicalCard, int}> $cards
     * @param list<string>                  $roleTraits
     *
     * @return list<string>
     */
    private function validateRoleRestrictions(array $cards, array $roleTraits): array
    {
        $errors = [];
        foreach ($cards as [$card]) {
            $restriction = $card->getRoleRestriction();
            if ($restriction instanceof Role && ! in_array($restriction->value, $roleTraits, true)) {
                $errors[] = sprintf('%s requires a %s role.', $card->getName(), $restriction->value);
            }
        }

        return $errors;
    }

    /**
     * Exactly 5 provinces with different titles, one per element; a Seeker role allows to replace the province of any
     * element with an additional province of its element. A province of several elements stands for any of them.
     *
     * @param list<array{LogicalCard, int}> $provinces
     * @param list<string>                  $roleTraits
     *
     * @return list<string>
     */
    private function validateProvinces(array $provinces, array $roleTraits): array
    {
        $count = array_sum(array_column($provinces, 1));
        if ($count !== self::PROVINCES) {
            return [sprintf('The deck must have exactly %d provinces, it has %d.', self::PROVINCES, $count)];
        }

        $errors = [];
        foreach ($provinces as [$province, $quantity]) {
            if ($quantity > 1) {
                $errors[] = sprintf('The deck can have at most 1 copy of the province %s.', $province->getName());
            }
        }
        $titles = array_map(fn (array $province): string => $province[0]->getName(), $provinces);
        if (count(array_unique($titles)) !== count($titles)) {
            $errors[] = 'The provinces must have different titles.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $elements = Element::cases();
        $slotSets = [$elements];
        $seekerElement = in_array(Role::Seeker->value, $roleTraits, true) ? $this->roleElement($roleTraits) : null;
        if ($seekerElement instanceof Element) {
            foreach ($elements as $index => $replaced) {
                if ($replaced !== $seekerElement) {
                    $slots = $elements;
                    $slots[$index] = $seekerElement;
                    $slotSets[] = $slots;
                }
            }
        }

        $provinceElements = array_map(fn (array $province): array => $province[0]->getElements() ?? [], $provinces);
        foreach ($slotSets as $slots) {
            if ($this->canAssign($provinceElements, $slots)) {
                return [];
            }
        }

        return ['The provinces must be associated with each element, one province per element.'];
    }

    /**
     * Whether each province can fill a different slot of one of its elements.
     *
     * @param list<list<Element>> $provinceElements
     * @param list<Element>       $slots
     */
    private function canAssign(array $provinceElements, array $slots): bool
    {
        if ($provinceElements === []) {
            return true;
        }

        $elements = array_shift($provinceElements);
        foreach ($slots as $index => $slot) {
            if (in_array($slot, $elements, true)) {
                $remaining = $slots;
                unset($remaining[$index]);
                if ($this->canAssign($provinceElements, array_values($remaining))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $roleTraits
     */
    private function roleElement(array $roleTraits): ?Element
    {
        foreach ($roleTraits as $trait) {
            $element = Element::tryFrom($trait);
            if ($element instanceof Element) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Provinces and dynasty cards in-clan or neutral; conflict cards in-clan, neutral, or out-of-clan with an influence
     * cost, from a single other clan, within the influence of the stronghold and the role.
     *
     * @param list<string>                  $roleTraits
     * @param list<array{LogicalCard, int}> $cards
     *
     * @return list<string>
     */
    private function validateClans(LogicalCard $stronghold, array $roleTraits, array $cards): array
    {
        $clan = $stronghold->getClan() ?? Clan::Neutral;
        $supportedClan = null;
        foreach ($roleTraits as $trait) {
            $supportedClan ??= Clan::tryFrom($trait);
        }

        $influence = ($stronghold->getInfluencePool() ?? 0)
            + (in_array(Role::Keeper->value, $roleTraits, true) ? self::KEEPER_INFLUENCE : 0)
            + ($supportedClan instanceof Clan ? self::SUPPORT_INFLUENCE : 0);

        $errors = [];
        $spent = 0;
        $splashedClans = [];
        foreach ($cards as [$card, $quantity]) {
            if ($this->isInClanOrNeutral($card, $clan)) {
                continue;
            }
            if ($card->getSide() !== Side::Conflict || $card->getInfluenceCost() === null) {
                $errors[] = sprintf('%s is not in-clan nor neutral.', $card->getName());
                continue;
            }
            $spent += $card->getInfluenceCost() * $quantity;
            $splashedClans[$card->getClan()->value ?? ''] = true;
        }

        if (count($splashedClans) > 1) {
            $errors[] = 'The out-of-clan conflict cards must come from a single clan.';
        }
        if ($supportedClan instanceof Clan && array_diff(array_keys($splashedClans), [$supportedClan->value]) !== []) {
            $errors[] = sprintf('The influence can only be spent on %s cards.', $supportedClan->value);
        }
        if ($spent > $influence) {
            $errors[] = sprintf('The out-of-clan cards cost %d influence, the deck has %d.', $spent, $influence);
        }

        return $errors;
    }

    private function isInClanOrNeutral(LogicalCard $card, Clan $clan): bool
    {
        return $card->getClan() === Clan::Neutral
            || $card->getClan() === $clan
            || in_array($clan->value, $card->getAllowedClans() ?? [], true);
    }
}
