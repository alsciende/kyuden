<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProvinceCard extends PhysicalCard
{
    public const string STATE_FACEDOWN = 'province_facedown';
    public const string STATE_UNBROKEN = 'province_unbroken';
    public const string STATE_BROKEN = 'province_broken';

    #[\Override]
    public function getAllowedTypes(): array
    {
        return [
            Type::Province,
        ];
    }
}
