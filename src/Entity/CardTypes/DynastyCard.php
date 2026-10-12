<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use App\Enum\StackType;
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
    public static function getInitialOrientation(): Orientation
    {
        return Orientation::FaceDown;
    }

    #[\Override]
    public static function getPlace(StackType $stackType, Orientation $orientation): ?string
    {
        return match ([$stackType, $orientation]) {
            [StackType::DynastyDrawDeck, Orientation::FaceDown] => self::STATE_DRAW_DECK,
            [StackType::RegularProvince, Orientation::FaceDown], [StackType::StrongholdProvince, Orientation::FaceDown] => self::STATE_PROVINCE_FACEDOWN,
            [StackType::RegularProvince, Orientation::FaceUp], [StackType::StrongholdProvince, Orientation::FaceUp] => self::STATE_PROVINCE_FACEUP,
            // a bowed card (Sideways) has the same transitions as a ready one
            [StackType::InPlay, Orientation::FaceUp], [StackType::InPlay, Orientation::Sideways] => self::STATE_IN_PLAY,
            [StackType::DynastyDiscardPile, Orientation::FaceUp] => self::STATE_DISCARD_PILE,
            default => null,
        };
    }

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
