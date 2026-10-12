<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RoleCard extends PhysicalCard
{
    public const string STATE_IN_PLAY = 'in_play';

    #[\Override]
    public static function getInitialOrientation(): Orientation
    {
        return Orientation::FaceUp;
    }

    #[\Override]
    public static function getPlace(StackType $stackType, Orientation $orientation): ?string
    {
        return $stackType === StackType::Role && $orientation === Orientation::FaceUp ? self::STATE_IN_PLAY : null;
    }

    #[\Override]
    public function getAllowedTypes(): array
    {
        return [
            Type::Role,
        ];
    }
}
