<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\PhysicalCard;
use App\Enum\InterruptStage;

/**
 * An interrupt a card can trigger to what is about to happen, as its script tells it (AbstractCardScript, the methods
 * of getSubscribedEvents() for an imminent triggering condition): the card offered to its controller, in the stage of
 * the window its text belongs to.
 */
final readonly class InterruptOffer
{
    public function __construct(
        public PhysicalCard $card,
        /**
         * A forced interrupt ("Forced Interrupt:"), not the choice of its controller.
         */
        public bool $forced = false,
        /**
         * An interrupt to what "would" occur, which may cancel or replace it ("would… instead").
         */
        public bool $would = false,
    ) {
    }

    public function stage(): InterruptStage
    {
        return match (true) {
            $this->forced => InterruptStage::Forced,
            $this->would => InterruptStage::Would,
            default => InterruptStage::Standard,
        };
    }
}
