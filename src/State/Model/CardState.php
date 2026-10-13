<?php

declare(strict_types=1);

namespace App\State\Model;

/**
 * What is not a physical position of a card: its stack and orientation are on PhysicalCard.
 */
class CardState
{
    /**
     * Fate tokens on the card.
     */
    private int $fate = 0;

    public function getFate(): int
    {
        return $this->fate;
    }

    public function setFate(int $fate): self
    {
        $this->fate = $fate;

        return $this;
    }

    public function incrementFate(int $gain): self
    {
        return $this->setFate($this->fate + $gain);
    }
}
