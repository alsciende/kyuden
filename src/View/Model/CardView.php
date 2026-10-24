<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Enum\Side;

/**
 * A card as the viewing player sees it. A card the viewer cannot see (face down, except their own provinces) has no
 * code: the client knows the cards by code. The skills are those set by the players (`SetCharacterSkill`), every
 * modifier applied; null: the printed skill. Its side (conflict, dynasty, province…) is public: it tells the back of the
 * card, which every player sees.
 */
final readonly class CardView
{
    public function __construct(
        public string $id,
        public ?string $code,
        public Side $side,
        public Orientation $orientation,
        public int $fate,
        public PersonalHonor $personalHonor,
        public ?int $militarySkill,
        public ?int $politicalSkill,
    ) {
    }
}
