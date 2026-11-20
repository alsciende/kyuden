<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Enum\Type;

/**
 * A player has played a card: a character or an attachment once it has entered play (after CardEnteredPlayEvent,
 * once it has joined the conflict when it enters it). Playing is not putting into play. The triggering condition of
 * the cards reacting to a card played ("After you play this character", "After you play a [clan-crane] character"),
 * which read the conflict in the state. Not logged: the transition writes its entry.
 */
final readonly class CardPlayedEvent implements TriggeringEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        /**
         * The player who played it.
         */
        public Player $player,
        /**
         * The stack it was played from.
         */
        public StackType $from,
        /**
         * The fate the player spent to play it.
         */
        public int $fateSpent,
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
        return 'card_played';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'card' => $this->card->getId(),
            'player' => $this->player->getId(),
        ];
    }

    /**
     * It was played from a province ("from your provinces").
     */
    public function isFromProvince(): bool
    {
        return in_array($this->from, [StackType::RegularProvince, StackType::StrongholdProvince], true);
    }

    /**
     * The card played is of that type ("After you play a character").
     */
    public function isOfType(Type $type): bool
    {
        return $this->card->getLogicalCard()->getType() === $type;
    }
}
