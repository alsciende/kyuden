<?php

declare(strict_types=1);

namespace App\Entity\CardTypes;

use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ConflictCard extends PhysicalCard
{
    public const string STATE_DRAW_DECK = 'conflict_draw_deck';
    public const string STATE_HAND = 'hand';
    public const string STATE_IN_PLAY = 'in_play';
    // a character in play as an attachment, attached to another character (PhysicalCard::getCurrentPlace(): the stack
    // and the orientation are those of an attachment)
    public const string STATE_ATTACHED = 'attached';
    public const string STATE_DISCARD_PILE = 'conflict_discard_pile';
    // out of play, face up or face down: the transitions are the same
    public const string STATE_REMOVED_FROM_GAME = 'removed_from_game';
    public const string STATE_UNDERNEATH = 'underneath';

    #[\Override]
    public static function getInitialOrientation(): Orientation
    {
        return Orientation::FaceDown;
    }

    #[\Override]
    public static function getPlace(StackType $stackType, Orientation $orientation): ?string
    {
        return match ([$stackType, $orientation]) {
            [StackType::ConflictDrawDeck, Orientation::FaceDown] => self::STATE_DRAW_DECK,
            [StackType::Hand, Orientation::FaceUp] => self::STATE_HAND,
            // a bowed card (Sideways) has the same transitions as a ready one
            [StackType::InPlay, Orientation::FaceUp], [StackType::InPlay, Orientation::Sideways] => self::STATE_IN_PLAY,
            // an attachment on a province
            [StackType::RegularProvince, Orientation::FaceUp], [StackType::RegularProvince, Orientation::Sideways],
            [StackType::StrongholdProvince, Orientation::FaceUp], [StackType::StrongholdProvince, Orientation::Sideways] => self::STATE_IN_PLAY,
            [StackType::ConflictDiscardPile, Orientation::FaceUp] => self::STATE_DISCARD_PILE,
            [StackType::RemovedFromGame, Orientation::FaceUp], [StackType::RemovedFromGame, Orientation::FaceDown] => self::STATE_REMOVED_FROM_GAME,
            [StackType::Underneath, Orientation::FaceUp], [StackType::Underneath, Orientation::FaceDown] => self::STATE_UNDERNEATH,
            default => null,
        };
    }

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
