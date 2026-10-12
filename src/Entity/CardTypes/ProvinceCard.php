<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ProvinceCard extends PhysicalCard
{
    public const string STATE_FACEDOWN = 'province_facedown';
    public const string STATE_UNBROKEN = 'province_unbroken';
    public const string STATE_BROKEN = 'province_broken';

    #[\Override]
    public static function getInitialOrientation(): Orientation
    {
        return Orientation::FaceDown;
    }

    #[\Override]
    public static function getPlace(StackType $stackType, Orientation $orientation): ?string
    {
        if ($stackType !== StackType::RegularProvince && $stackType !== StackType::StrongholdProvince) {
            return null;
        }

        return match ($orientation) {
            Orientation::FaceDown => self::STATE_FACEDOWN,
            Orientation::FaceUp => self::STATE_UNBROKEN,
            Orientation::UpsideDown => self::STATE_BROKEN,
            Orientation::Sideways => null,
        };
    }

    #[\Override]
    public function getAllowedTypes(): array
    {
        return [
            Type::Province,
        ];
    }
}
