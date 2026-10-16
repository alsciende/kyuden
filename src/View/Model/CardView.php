<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Orientation;
use App\Enum\PersonalHonor;

/**
 * A card as the viewing player sees it. A card the viewer cannot see (face down, except their own provinces) has no
 * code: the client knows the cards by code. The skills are those set by the players (`SetCharacterSkill`), every
 * modifier applied; null: the printed skill.
 */
final readonly class CardView
{
    public function __construct(
        public string $id,
        public ?string $code,
        public Orientation $orientation,
        public int $fate,
        public PersonalHonor $personalHonor,
        public ?int $militarySkill,
        public ?int $politicalSkill,
    ) {
    }
}
