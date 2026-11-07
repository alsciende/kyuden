<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\ConflictType;
use App\Exception\Rules\HonorNotSetException;
use App\Exception\Rules\NoConflictOpportunityException;
use App\Exception\Rules\NotEnoughFateException;

class PlayerState
{
    /**
     * Stronghold format: a player with no honor is eliminated, a player with 25 or more honor wins the game.
     */
    public const int MINIMUM_HONOR = 0;
    public const int MAXIMUM_HONOR = 25;

    /**
     * Stronghold format: one opportunity to declare a military conflict and one to declare a political conflict.
     */
    public const int CONFLICT_OPPORTUNITIES = 2;

    private ?string $strongholdProvinceId = null;
    private ?int $honor = null;
    private int $fate = 0;

    /**
     * Honor bid: the number shown on the honor dial once revealed. It stays as a reference until the next bid.
     */
    private ?int $honorBid = null;

    /**
     * Number secretly selected on the honor dial, until the dials are revealed.
     */
    private ?int $pendingHonorBid = null;

    /**
     * Turn records: what the player has done during the current round. Reset at the end of the round.
     */
    private bool $fateCollected = false;

    /**
     * Conflict opportunities left this round: declaring a conflict or passing a conflict opportunity uses one.
     */
    private int $conflictOpportunities = self::CONFLICT_OPPORTUNITIES;

    /**
     * Glory count declared by the player during the conflict phase, claimed rings excluded.
     */
    private ?int $gloryCount = null;

    /**
     * Types of the conflicts declared this round: a player declares at most one conflict of each type.
     *
     * @var list<ConflictType>
     */
    private array $declaredConflictTypes = [];

    /**
     * Types of the additional conflicts granted by card effects this round (GrantConflictOpportunity): each one lets the
     * player declare one more conflict of that type.
     *
     * @var list<ConflictType>
     */
    private array $additionalConflictTypes = [];

    /**
     * The player has passed during the current step. Reset when a step in which players can pass begins.
     */
    private bool $passed = false;

    /**
     * The player has taken an action during their opportunity to act (played a card, triggered an action ability) and
     * resolves it: they end it (EndResolution), which ends their opportunity, instead of passing.
     */
    private bool $acted = false;

    /**
     * The events played and the abilities triggered that the player resolves, in the order they were initiated: those
     * of their action, ended together with it, and the interrupts and reactions, whether or not they have the
     * opportunity to act, each one ending with EndResolution, the last one initiated first (interrupts and reactions
     * do not use the opportunity to act). Characters and attachments played are not resolutions: only `acted` records
     * them.
     *
     * @var list<Resolution>
     */
    private array $resolutions = [];

    /**
     * The player has conceded the game: they are eliminated.
     */
    private bool $conceded = false;

    /**
     * The player has stopped playing, and their opponent has ended the game (ClaimAbandonment): they are eliminated.
     */
    private bool $abandoned = false;

    public function setStrongholdProvinceId(?string $strongholdProvinceId): void
    {
        $this->strongholdProvinceId = $strongholdProvinceId;
    }

    public function getStrongholdProvinceId(): ?string
    {
        return $this->strongholdProvinceId;
    }

    public function getHonor(): ?int
    {
        return $this->honor;
    }

    /**
     * A player cannot lose honor they do not have: honor stops at the minimum. Reaching the minimum or the maximum
     * ends the game (`end_game` transition).
     */
    public function setHonor(?int $honor): self
    {
        $this->honor = $honor === null ? null : max($honor, self::MINIMUM_HONOR);

        return $this;
    }

    /**
     * @throws HonorNotSetException before the honor is set (setup)
     */
    public function incrementHonor(int $gain): self
    {
        return $this->setHonor($this->getSetHonor() + $gain);
    }

    /**
     * @throws HonorNotSetException before the honor is set (setup)
     */
    public function decrementHonor(int $loss): self
    {
        return $this->setHonor($this->getSetHonor() - $loss);
    }

    private function getSetHonor(): int
    {
        return $this->honor ?? throw new HonorNotSetException();
    }

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
     * Spends or loses fate from the fate pool, to the general token pool.
     *
     * @throws NotEnoughFateException if the fate pool does not hold that much fate
     */
    public function spendFate(int $amount): self
    {
        if ($amount > $this->fate) {
            throw new NotEnoughFateException($amount, $this->fate);
        }

        return $this->setFate($this->fate - $amount);
    }

    public function isFateCollected(): bool
    {
        return $this->fateCollected;
    }

    public function setFateCollected(bool $fateCollected): self
    {
        $this->fateCollected = $fateCollected;

        return $this;
    }

    /**
     * Revealed honor bid, which card effects can change; null from the start of a bid until the dials are revealed.
     */
    public function getHonorBid(): ?int
    {
        return $this->honorBid;
    }

    public function setHonorBid(?int $honorBid): self
    {
        $this->honorBid = $honorBid;

        return $this;
    }

    /**
     * Secret honor bid, known only to the player; null until the player has bid, and once the dials are revealed.
     */
    public function getPendingHonorBid(): ?int
    {
        return $this->pendingHonorBid;
    }

    public function setPendingHonorBid(?int $pendingHonorBid): self
    {
        $this->pendingHonorBid = $pendingHonorBid;

        return $this;
    }

    /**
     * A new honor bid begins: the previous one is no longer a reference while the dial is being turned.
     */
    public function startHonorBid(): self
    {
        return $this->setHonorBid(null)->setPendingHonorBid(null);
    }

    /**
     * The honor dials are revealed: the secret bid becomes the honor bid.
     */
    public function revealHonorBid(): self
    {
        return $this->setHonorBid($this->pendingHonorBid)->setPendingHonorBid(null);
    }

    public function getConflictOpportunities(): int
    {
        return $this->conflictOpportunities;
    }

    public function setConflictOpportunities(int $conflictOpportunities): self
    {
        $this->conflictOpportunities = $conflictOpportunities;

        return $this;
    }

    /**
     * @return list<ConflictType>
     */
    public function getDeclaredConflictTypes(): array
    {
        return $this->declaredConflictTypes;
    }

    /**
     * @param list<ConflictType> $declaredConflictTypes
     */
    public function setDeclaredConflictTypes(array $declaredConflictTypes): self
    {
        $this->declaredConflictTypes = $declaredConflictTypes;

        return $this;
    }

    /**
     * @return list<ConflictType>
     */
    public function getAdditionalConflictTypes(): array
    {
        return $this->additionalConflictTypes;
    }

    /**
     * @param list<ConflictType> $additionalConflictTypes
     */
    public function setAdditionalConflictTypes(array $additionalConflictTypes): self
    {
        $this->additionalConflictTypes = $additionalConflictTypes;

        return $this;
    }

    /**
     * An additional conflict of that type: one more conflict opportunity, and one more conflict of that type allowed.
     */
    public function grantConflictOpportunity(ConflictType $type): self
    {
        $this->conflictOpportunities++;
        $this->additionalConflictTypes[] = $type;

        return $this;
    }

    /**
     * The player may declare a conflict of that type: an opportunity is left, and fewer conflicts of that type were
     * declared than allowed (one, plus the additional conflicts of that type).
     */
    public function canDeclareConflict(ConflictType $type): bool
    {
        return $this->conflictOpportunities > 0
            && $this->countOf($type, $this->declaredConflictTypes) <= $this->countOf($type, $this->additionalConflictTypes);
    }

    /**
     * The types of the conflicts the player may still declare this round. Not a getter: the state is serialized
     * through them.
     *
     * @return list<ConflictType>
     */
    public function declarableConflictTypes(): array
    {
        return array_values(array_filter(ConflictType::cases(), $this->canDeclareConflict(...)));
    }

    /**
     * Uses a conflict opportunity: to declare a conflict of that type, or to pass (no type).
     *
     * @throws NoConflictOpportunityException if no opportunity is left, or no more conflict of that type is allowed
     */
    public function useConflictOpportunity(?ConflictType $type): self
    {
        if ($this->conflictOpportunities <= 0 || ($type instanceof ConflictType && ! $this->canDeclareConflict($type))) {
            throw new NoConflictOpportunityException($type);
        }

        $this->conflictOpportunities--;
        if ($type instanceof ConflictType) {
            $this->declaredConflictTypes[] = $type;
        }

        return $this;
    }

    /**
     * @param list<ConflictType> $types
     */
    private function countOf(ConflictType $type, array $types): int
    {
        return count(array_filter($types, fn (ConflictType $candidate): bool => $candidate === $type));
    }

    public function getGloryCount(): ?int
    {
        return $this->gloryCount;
    }

    public function setGloryCount(?int $gloryCount): self
    {
        $this->gloryCount = $gloryCount;

        return $this;
    }

    /**
     * The player has passed during the current step: the opportunity to act skips them.
     */
    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed): self
    {
        $this->passed = $passed;

        return $this;
    }

    public function hasActed(): bool
    {
        return $this->acted;
    }

    public function setActed(bool $acted): self
    {
        $this->acted = $acted;

        return $this;
    }

    /**
     * @return list<Resolution>
     */
    public function getResolutions(): array
    {
        return $this->resolutions;
    }

    /**
     * @param list<Resolution> $resolutions
     */
    public function setResolutions(array $resolutions): self
    {
        $this->resolutions = $resolutions;

        return $this;
    }

    public function startResolution(Resolution $resolution): self
    {
        $this->resolutions[] = $resolution;

        return $this;
    }

    /**
     * Number of interrupts and reactions the player has initiated and has not ended yet. Not prefixed with `get`, so
     * that the serializer of the state does not take it for a property.
     */
    public function countInterruptsAndReactions(): int
    {
        return count(array_filter($this->resolutions, fn (Resolution $resolution): bool => $resolution->interruptOrReaction()));
    }

    /**
     * Ends the last interrupt or reaction initiated.
     */
    public function endInterruptOrReaction(): self
    {
        foreach (array_reverse(array_keys($this->resolutions)) as $index) {
            if ($this->resolutions[$index]->interruptOrReaction()) {
                array_splice($this->resolutions, $index, 1);

                break;
            }
        }

        return $this;
    }

    /**
     * Ends the action of the player, with the events and the action abilities resolved in it.
     */
    public function endAction(): self
    {
        $this->resolutions = array_values(array_filter($this->resolutions, fn (Resolution $resolution): bool => $resolution->interruptOrReaction()));

        return $this->setActed(false);
    }

    /**
     * The player resolves what they have played (an action, an interrupt or a reaction) and has not ended it yet.
     */
    public function isResolving(): bool
    {
        return $this->acted || $this->countInterruptsAndReactions() > 0;
    }

    public function isConceded(): bool
    {
        return $this->conceded;
    }

    public function setConceded(bool $conceded): self
    {
        $this->conceded = $conceded;

        return $this;
    }

    public function isAbandoned(): bool
    {
        return $this->abandoned;
    }

    public function setAbandoned(bool $abandoned): self
    {
        $this->abandoned = $abandoned;

        return $this;
    }

    public function resetTurnRecords(): self
    {
        return $this->setFateCollected(false)
            ->setConflictOpportunities(self::CONFLICT_OPPORTUNITIES)
            ->setDeclaredConflictTypes([])
            ->setAdditionalConflictTypes([])
            ->setGloryCount(null);
    }
}
