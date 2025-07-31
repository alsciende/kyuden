<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StrongholdCard extends PhysicalCard
{
    public const string STATE_IN_PLAY = 'in_play';

    #[\Override]
    public function getAllowedTypes(): array
    {
        return [
            Type::Stronghold,
        ];
    }
}
