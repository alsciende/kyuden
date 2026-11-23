<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\GameLog\LogParam;

/**
 * An attachment would be played onto a character (PlayAttachment, PlayCharacterAsAttachment), before its cost is paid
 * (Iron Mountain Castle reduces it). Nothing cancels a play: cancelled, the message is still handled.
 */
final readonly class AttachmentBeingPlayed implements ImminentTriggerInterface
{
    public function __construct(
        public PhysicalCard $card,
        /**
         * The player playing it, who will control it.
         */
        public Player $player,
        /**
         * The character it would be attached to.
         */
        public PhysicalCard $target,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'attachment_being_played';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return true;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'card' => $this->card->getId(),
            'code' => $this->card->getLogicalCard()->getId(),
            'player' => $this->player->getId(),
            'target' => $this->target->getId(),
        ];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{player} is about to play {card} on {target}';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->player, 'card' => LogParam::card($this->card, null, null), 'target' => $this->target];
    }
}
