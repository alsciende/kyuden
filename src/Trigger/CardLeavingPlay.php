<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * A card in play would leave play: discarded, sacrificed, returned to the hand or a deck, removed from the game,
 * placed under a card, put into a province, or discarded in the fate phase for having no fate. A character takes its
 * attachments with it: each of them would leave play too, caused by it (`host`).
 */
final readonly class CardLeavingPlay implements ImminentTriggerInterface
{
    public function __construct(
        public PhysicalCard $card,
        /**
         * The player who controls it in play.
         */
        public Player $controller,
        /**
         * It is a character in play, not an attachment.
         */
        public bool $character,
        /**
         * It would be sacrificed (SacrificeCard).
         */
        public bool $sacrificed = false,
        /**
         * The character it is attached to, which would leave play and take it along; null for the card leaving play
         * itself.
         */
        public ?PhysicalCard $host = null,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'card_leaving_play';
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        $params = ['card' => $this->card->getId(), 'controller' => $this->controller->getId()];
        if ($this->host instanceof PhysicalCard) {
            $params['host'] = $this->host->getId();
        }

        return $params;
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{card} would leave play';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['card' => $this->card];
    }
}
