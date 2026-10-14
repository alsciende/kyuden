<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Enum\ConflictType;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\State\Model\PlayerState;
use App\Enum\StackType;

/**
 * Describes the board of one player. Nothing is created until GameBuilder::build() is called.
 *
 * Methods taking a list of card codes accept string keys as aliases:
 * `->hand(['banzai' => '01-banzai', '01-fine-katana'])` lets the test retrieve the first card with
 * `$scenario->card('banzai')`. Cards are added to their stack in the given order: the last one is on top.
 *
 * Cards are face down in decks and provinces (except the stronghold), face up elsewhere, unless stated otherwise.
 */
final class PlayerBuilder
{
    private ?string $deckId = null;

    private ?string $starterDeckId = null;

    /**
     * @var array<string, string>
     */
    private array $starterDeckAliases = [];

    private ?string $chosenStrongholdProvince = null;

    private ?int $honor = null;

    private int $fate = 0;

    private bool $fateCollected = false;

    private ?int $honorBid = null;
    private ?int $pendingHonorBid = null;

    private bool $passed = false;

    private int $conflictOpportunities = PlayerState::CONFLICT_OPPORTUNITIES;

    /**
     * @var list<ConflictType>
     */
    private array $declaredConflictTypes = [];

    /**
     * @var list<PlannedStack>
     */
    private array $stacks = [];

    public function __construct(
        public readonly string $alias,
    ) {
    }

    /**
     * Lays out a whole deck like the start_game transition does: every card in its draw deck,
     * provinces and stronghold in the stronghold province stack, no stronghold province chosen yet.
     *
     * @param array<string, string> $aliases alias => card code, applied to the first copy of that card
     */
    public function fromStarterDeck(string $deckId, array $aliases = []): self
    {
        $this->deckId = $deckId;
        $this->starterDeckId = $deckId;
        $this->starterDeckAliases = $aliases;

        return $this;
    }

    /**
     * Deck the player registered with. Defaults to the deck given to fromStarterDeck(), or any starter deck.
     */
    public function deck(string $deckId): self
    {
        $this->deckId = $deckId;

        return $this;
    }

    public function honor(int $honor): self
    {
        $this->honor = $honor;

        return $this;
    }

    public function fate(int $fate): self
    {
        $this->fate = $fate;

        return $this;
    }

    /**
     * Records that the player has already collected fate this round.
     */
    public function fateCollected(): self
    {
        $this->fateCollected = true;

        return $this;
    }

    /**
     * Records the revealed honor bid of the player.
     */
    public function honorBid(int $bid): self
    {
        $this->honorBid = $bid;

        return $this;
    }

    /**
     * Records the secret honor bid of the player, before the honor dials are revealed.
     */
    public function pendingHonorBid(int $bid): self
    {
        $this->pendingHonorBid = $bid;

        return $this;
    }

    /**
     * Records that the player has passed during the current step.
     */
    public function passed(): self
    {
        $this->passed = true;

        return $this;
    }

    /**
     * Records the conflict opportunities the player has left this round.
     */
    public function conflictOpportunities(int $conflictOpportunities): self
    {
        $this->conflictOpportunities = $conflictOpportunities;

        return $this;
    }

    /**
     * Records the conflicts the player has declared this round, using one conflict opportunity each.
     */
    public function declaredConflicts(ConflictType ...$types): self
    {
        $this->declaredConflictTypes = array_values($types);
        $this->conflictOpportunities -= count($types);

        return $this;
    }

    /**
     * Records the card with that alias as the stronghold province in the player state.
     */
    public function chooseStrongholdProvince(string $alias): self
    {
        $this->chosenStrongholdProvince = $alias;

        return $this;
    }

    public function stronghold(string $code, ?string $as = null): self
    {
        $this->stack(StackType::StrongholdProvince)->cards[] = new PlannedCard($code, $as);

        return $this;
    }

    /**
     * Adds a province to the stronghold province stack. When $chosen is true, it is also recorded
     * as the stronghold province in the player state.
     */
    public function strongholdProvince(string $code, ?string $as = null, bool $chosen = true, ?Orientation $orientation = null): self
    {
        $this->stack(StackType::StrongholdProvince)->cards[] = new PlannedCard($code, $as, $orientation, chosenStrongholdProvince: $chosen);

        return $this;
    }

    /**
     * Creates a regular province stack holding the province and, optionally, a dynasty card on it.
     */
    public function province(
        string $code,
        ?string $as = null,
        ?string $dynasty = null,
        ?string $dynastyAs = null,
        bool $dynastyFaceup = false,
        Orientation $orientation = Orientation::FaceDown,
    ): self {
        $stack = new PlannedStack(StackType::RegularProvince);
        $stack->cards[] = new PlannedCard($code, $as, $orientation);
        if ($dynasty !== null) {
            $stack->cards[] = new PlannedCard($dynasty, $dynastyAs, $dynastyFaceup ? Orientation::FaceUp : Orientation::FaceDown);
        }
        $this->stacks[] = $stack;

        return $this;
    }

    /**
     * @param array<array-key, string> $codes
     */
    public function hand(array $codes): self
    {
        return $this->addCards(StackType::Hand, $codes);
    }

    /**
     * @param array<array-key, string> $codes
     */
    public function conflictDeck(array $codes): self
    {
        return $this->addCards(StackType::ConflictDrawDeck, $codes);
    }

    /**
     * @param array<array-key, string> $codes
     */
    public function conflictDiscardPile(array $codes): self
    {
        return $this->addCards(StackType::ConflictDiscardPile, $codes);
    }

    /**
     * @param array<array-key, string> $codes
     */
    public function dynastyDeck(array $codes): self
    {
        return $this->addCards(StackType::DynastyDrawDeck, $codes);
    }

    /**
     * @param array<array-key, string> $codes
     */
    public function dynastyDiscardPile(array $codes): self
    {
        return $this->addCards(StackType::DynastyDiscardPile, $codes);
    }

    public function role(string $code, ?string $as = null): self
    {
        return $this->addCards(StackType::Role, $as === null ? [$code] : [
            $as => $code,
        ]);
    }

    /**
     * Creates one in-play stack: a character followed by the attachments put on it. The bowed status (sideways), the
     * fate and the personal honor apply to the character.
     *
     * @param array<array-key, string> $codes
     */
    public function inPlay(array $codes, bool $bowed = false, int $fate = 0, PersonalHonor $personalHonor = PersonalHonor::Ordinary): self
    {
        $stack = new PlannedStack(StackType::InPlay);
        foreach ($codes as $as => $code) {
            $stack->cards[] = $stack->cards === []
                ? new PlannedCard($code, is_string($as) ? $as : null, $bowed ? Orientation::Sideways : null, fate: $fate, personalHonor: $personalHonor)
                : new PlannedCard($code, is_string($as) ? $as : null);
        }
        $this->stacks[] = $stack;

        return $this;
    }

    public function getDeckId(): ?string
    {
        return $this->deckId;
    }

    public function getStarterDeckId(): ?string
    {
        return $this->starterDeckId;
    }

    /**
     * @return array<string, string>
     */
    public function getStarterDeckAliases(): array
    {
        return $this->starterDeckAliases;
    }

    public function getChosenStrongholdProvince(): ?string
    {
        return $this->chosenStrongholdProvince;
    }

    public function getHonor(): ?int
    {
        return $this->honor;
    }

    public function getFate(): int
    {
        return $this->fate;
    }

    public function isFateCollected(): bool
    {
        return $this->fateCollected;
    }

    public function getHonorBid(): ?int
    {
        return $this->honorBid;
    }

    public function getPendingHonorBid(): ?int
    {
        return $this->pendingHonorBid;
    }

    public function getConflictOpportunities(): int
    {
        return $this->conflictOpportunities;
    }

    /**
     * @return list<ConflictType>
     */
    public function getDeclaredConflictTypes(): array
    {
        return $this->declaredConflictTypes;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    /**
     * @return list<PlannedStack>
     */
    public function getStacks(): array
    {
        return $this->stacks;
    }

    /**
     * @param array<array-key, string> $codes
     */
    private function addCards(StackType $type, array $codes): self
    {
        $stack = $this->stack($type);
        foreach ($codes as $as => $code) {
            $stack->cards[] = new PlannedCard($code, is_string($as) ? $as : null);
        }

        return $this;
    }

    /**
     * Returns the planned unique stack of that type, creating it if needed.
     */
    private function stack(StackType $type): PlannedStack
    {
        foreach ($this->stacks as $stack) {
            if ($stack->type === $type) {
                return $stack;
            }
        }

        $stack = new PlannedStack($type);
        $this->stacks[] = $stack;

        return $stack;
    }
}
