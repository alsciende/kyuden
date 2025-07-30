<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ConflictCard extends PhysicalCard
{
    public const string STATE_DRAW_DECK = 'conflict_draw_deck';
    public const string STATE_HAND = 'hand';
    public const string STATE_IN_PLAY = 'in_play';
    public const string STATE_DISCARD_PILE = 'conflict_discard_pile';

    #[\Override]
    public function getAllowedTypes(): array
    {
        return [
            Type::Attachment,
            Type::Character,
            Type::Event,
        ];
    }
}
