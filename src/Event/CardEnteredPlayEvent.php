<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;

/**
 * A character or an attachment has entered play, played (PlayCharacter, PlayCharacterFromProvince, PlayAttachment,
 * PlayCharacterAsAttachment) or put into play (PutCharacterIntoPlay, PutAttachmentIntoPlay), once it has joined the
 * conflict when it enters it: the triggering condition of the cards reacting to a card entering play ("After this
 * character enters play"), which read the conflict in the state. Not logged: the transition writes its entry.
 */
final readonly class CardEnteredPlayEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        /**
         * The player who controls it.
         */
        public Player $controller,
        /**
         * Played, not put into play.
         */
        public bool $played,
        /**
         * The stack it entered play from.
         */
        public StackType $from,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'card_entered_play';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'card' => $this->card->getId(),
            'player' => $this->controller->getId(),
        ];
    }

    /**
     * It entered play from a province ("enters play from a province").
     */
    public function isFromProvince(): bool
    {
        return in_array($this->from, [StackType::RegularProvince, StackType::StrongholdProvince], true);
    }
}
