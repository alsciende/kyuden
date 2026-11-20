<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use App\GameLog\GameLog;
use App\GameLog\LogParam;

/**
 * A player has revealed cards to every player (RevealCards, RevealCard).
 * The triggering condition of the cards reacting to cards of a hand looked at or revealed.
 */
#[GameLog('{player} reveals {cards} from {stack}')]
final readonly class CardsRevealedEvent implements TriggeringEventInterface
{
    /**
     * @param list<LogParam> $cards the params of the cards, with their visibility (Service/CardInspector)
     */
    public function __construct(
        public Game $game,
        public Player $player,
        public array $cards,
        public Stack $stack,
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
        return 'cards_revealed';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return [
            'player' => $this->player->getId(),
            'owner' => $this->stack->getPlayer()->getId(),
        ];
    }

    /**
     * The cards are in the hand of that player ("cards in an opponent's hand").
     */
    public function isHandOf(Player $player): bool
    {
        return $this->stack->getType() === StackType::Hand && $this->stack->getPlayer() === $player;
    }
}
