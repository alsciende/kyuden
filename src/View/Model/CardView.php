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
 * card, which every player sees. Honor tokens are counters on a province or a holding, not status tokens. Its controller is its owner unless another player has taken control of it in play; an
 * attachment may be controlled by another player than the one of the stack it is in.
 */
final readonly class CardView
{
    public function __construct(
        public string $id,
        public ?string $code,
        public Side $side,
        public Orientation $orientation,
        public int $fate,
        public int $honorTokens,
        public PersonalHonor $personalHonor,
        public ?int $militarySkill,
        public ?int $politicalSkill,
        public string $controllerId,
    ) {
    }
}
