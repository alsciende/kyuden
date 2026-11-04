<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\ConflictType;
use App\Enum\PersonalHonor;
use App\Exception\Rules\NotEnoughFateException;
use App\Exception\Rules\NoStatusTokenException;
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

    /**
     * Military skill of the character set by the players, every modifier applied; null: the printed skill.
     */
    private ?int $militarySkill = null;

    /**
     * Political skill of the character set by the players, every modifier applied; null: the printed skill.
     */
    private ?int $politicalSkill = null;

    /**
     * Player controlling the card in play, when it is not its owner (TakeControl); null: its owner.
     */
    private ?string $controllerId = null;

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

    /**
     * Its status token is discarded by an effect: the character returns to ordinary status without being honored nor
     * dishonored (glossary, "Personal Honor, Personal Dishonor").
     *
     * @throws NoStatusTokenException if the character has no status token
     */
    public function discardStatusToken(): self
    {
        if ($this->personalHonor === PersonalHonor::Ordinary) {
            throw new NoStatusTokenException();
        }

        return $this->setPersonalHonor(PersonalHonor::Ordinary);
    }

    /**
     * A status token is moved onto the character: an ordinary character takes its status, a character with the other
     * token discards both and returns to ordinary status (glossary, "Status Token").
     *
     * @throws PersonalHonorException if the character already has a token of this kind
     */
    public function receiveStatusToken(PersonalHonor $token): self
    {
        if ($token === PersonalHonor::Ordinary) {
            throw new \InvalidArgumentException('An ordinary status is not a token.');
        }

        return $this->setPersonalHonor(match ($this->personalHonor) {
            PersonalHonor::Ordinary => $token,
            $token => throw new PersonalHonorException($token),
            default => PersonalHonor::Ordinary,
        });
    }

    public function getMilitarySkill(): ?int
    {
        return $this->militarySkill;
    }

    public function setMilitarySkill(?int $militarySkill): self
    {
        $this->militarySkill = $militarySkill;

        return $this;
    }

    public function getPoliticalSkill(): ?int
    {
        return $this->politicalSkill;
    }

    public function setPoliticalSkill(?int $politicalSkill): self
    {
        $this->politicalSkill = $politicalSkill;

        return $this;
    }

    /**
     * Sets the skill of the given type, every modifier applied.
     */
    public function setSkill(ConflictType $type, int $skill): self
    {
        return match ($type) {
            ConflictType::Military => $this->setMilitarySkill($skill),
            ConflictType::Political => $this->setPoliticalSkill($skill),
        };
    }

    /**
     * Back to the printed skills.
     */
    public function resetSkills(): self
    {
        return $this->setMilitarySkill(null)->setPoliticalSkill(null);
    }

    public function getControllerId(): ?string
    {
        return $this->controllerId;
    }

    public function setControllerId(?string $controllerId): self
    {
        $this->controllerId = $controllerId;

        return $this;
    }
}
