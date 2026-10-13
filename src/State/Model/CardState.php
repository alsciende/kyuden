<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\PersonalHonor;
use App\Exception\Rules\NotEnoughFateException;
use App\Exception\Rules\PersonalHonorException;

/**
 * What is not a physical position of a card: its stack and orientation are on PhysicalCard. Reset when the card leaves
 * play (App\Workflow\LeavePlayListener).
 */
class CardState
{
    /**
     * Fate tokens on the card.
     */
    private int $fate = 0;

    private PersonalHonor $personalHonor = PersonalHonor::Ordinary;

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

    /**
     * @throws NotEnoughFateException if the card holds less fate
     */
    public function removeFate(int $amount): self
    {
        if ($amount > $this->fate) {
            throw new NotEnoughFateException($amount, $this->fate);
        }

        return $this->setFate($this->fate - $amount);
    }

    public function getPersonalHonor(): PersonalHonor
    {
        return $this->personalHonor;
    }

    public function setPersonalHonor(PersonalHonor $personalHonor): self
    {
        $this->personalHonor = $personalHonor;

        return $this;
    }

    /**
     * A dishonored character becomes ordinary, an ordinary one honored.
     *
     * @throws PersonalHonorException if the character is already honored
     */
    public function honor(): self
    {
        return $this->setPersonalHonor(match ($this->personalHonor) {
            PersonalHonor::Dishonored => PersonalHonor::Ordinary,
            PersonalHonor::Ordinary => PersonalHonor::Honored,
            PersonalHonor::Honored => throw new PersonalHonorException(PersonalHonor::Honored),
        });
    }

    /**
     * An honored character becomes ordinary, an ordinary one dishonored.
     *
     * @throws PersonalHonorException if the character is already dishonored
     */
    public function dishonor(): self
    {
        return $this->setPersonalHonor(match ($this->personalHonor) {
            PersonalHonor::Honored => PersonalHonor::Ordinary,
            PersonalHonor::Ordinary => PersonalHonor::Dishonored,
            PersonalHonor::Dishonored => throw new PersonalHonorException(PersonalHonor::Dishonored),
        });
    }
}
