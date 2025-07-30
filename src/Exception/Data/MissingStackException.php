<?php

declare(strict_types=1);

namespace App\Exception\Data;

use App\Entity\PhysicalCard;

class MissingStackException extends DataException
{
    public function __construct(
        private readonly PhysicalCard $card,
    ) {
        parent::__construct("Expected to find a stack for card: [{$this->card->getId()}]");
    }

    public function getCard(): PhysicalCard
    {
        return $this->card;
    }
}
