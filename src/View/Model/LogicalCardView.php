<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\AbilityTiming;
use App\Enum\Clan;
use App\Enum\Element;
use App\Enum\Role;
use App\Enum\Side;
use App\Enum\Type;

/**
 * A card as printed (LogicalCardViewFactory), known by its code: the views of the games and their logs send codes.
 * The packs it is printed in are not part of it, only the image of one of its printings.
 */
final readonly class LogicalCardView
{
    /**
     * @param list<string>|null    $traits
     * @param list<string>|null    $allowedClans
     * @param list<Element>|null   $elements
     * @param list<AbilityTiming>  $abilityTimings  timings of its triggered abilities, including those it grants
     * @param bool                 $abilityGrantedToAttached its abilities belong to the card it is attached to
     * @param string|null          $imageUrl        the image of its first printing that has one, for the clients
     */
    public function __construct(
        public string $code,
        public string $name,
        public Side $side,
        public Type $type,
        public ?Clan $clan,
        public ?bool $uniqueness,
        public ?int $cost,
        public ?array $traits,
        public ?string $text,
        public ?array $allowedClans,
        public ?int $deckLimit,
        public ?Role $roleRestriction,
        public ?int $influenceCost,
        public ?int $influencePool,
        public ?array $elements,
        public ?int $fate,
        public ?int $honor,
        public ?int $glory,
        public ?string $military,
        public ?string $militaryBonus,
        public ?string $political,
        public ?string $politicalBonus,
        public ?string $strength,
        public ?string $strengthBonus,
        public array $abilityTimings,
        public bool $abilityGrantedToAttached,
        public ?string $imageUrl,
    ) {
    }
}
