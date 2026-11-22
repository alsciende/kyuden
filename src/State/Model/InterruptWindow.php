<?php

declare(strict_types=1);

namespace App\State\Model;

/**
 * An interrupt window (glossary, Interrupts; InterruptOfferer): opened before imminent triggering conditions occur,
 * while what would make them occur waits (a message, an automatic transition). Its stages come one after the other,
 * each closing once every player asked has passed (PassInterrupt) and nobody resolves an interrupt initiated since it
 * opened; an interrupt initiated cancels the passes. A cancel (CancelImminentTrigger) removes conditions; once none is
 * left, or the last stage closes, the window closes, and what waits is done (InterruptWindowService).
 */
class InterruptWindow
{
    /**
     * @var list<ImminentTriggerRecord>
     */
    public array $triggers = [];

    /**
     * The stages still to come, the current one first; none asking nobody.
     *
     * @var list<InterruptWindowStage>
     */
    public array $stages = [];

    /**
     * The players who have passed in the current stage since the last interrupt was initiated.
     *
     * @var list<string>
     */
    public array $passedPlayerIds = [];

    /**
     * The number of interrupts and reactions each player was resolving when the window opened: those above it were
     * initiated in the window, which waits for them to end.
     *
     * @var array<string, int>
     */
    public array $resolutionBase = [];

    /**
     * The cards whose triggering condition a cancel has removed (the param `card`).
     *
     * @var list<string>
     */
    public array $cancelledCardIds = [];

    /**
     * What waits, a message (its class and its payload, as an ActionRecord), or an automatic transition of the game.
     */
    public ?string $messageName = null;
    public ?string $messagePayload = null;
    public ?string $transition = null;

    public function __construct(
        public string $id,
    ) {
    }

    /**
     * Not prefixed with `get`, `is` or `has`, so that the serializer of the state does not take it for a property;
     * same for the other methods.
     */
    public function currentStage(): ?InterruptWindowStage
    {
        return $this->stages[0] ?? null;
    }

    /**
     * The players of the current stage who have not passed since the last interrupt initiated.
     *
     * @return list<string>
     */
    public function awaitedPlayerIds(): array
    {
        return array_values(array_diff($this->currentStage()->askedPlayerIds ?? [], $this->passedPlayerIds));
    }
}
