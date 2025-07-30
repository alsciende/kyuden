<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class DynastyCard extends PhysicalCard
{
    public const string STATE_DRAW_DECK = 'dynasty_draw_deck';
    public const string STATE_PROVINCE_FACEDOWN = 'on_province_facedown';
    public const string STATE_PROVINCE_FACEUP = 'on_province_faceup';
    public const string STATE_IN_PLAY = 'in_play';
    public const string STATE_DISCARD_PILE = 'dynasty_discard_pile';

    #[\Override]
    public function getAllowedTypes(): array
    {
        return [
            Type::Character,
            Type::Event,
            Type::Holding,
        ];
    }
}
