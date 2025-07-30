<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProvinceCard extends PhysicalCard
{
    #[\Override]
    public function getAllowedTypes(): array
    {
        return [
            Type::Province,
            Type::Stronghold,
        ];
    }
}
