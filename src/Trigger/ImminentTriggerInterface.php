<?php

declare(strict_types=1);

namespace App\Trigger;

/**
 * A triggering condition about to occur ("When a character would leave play…"): what a message or a framework step
 * would do, before it is done. Not a domain event, nothing has changed yet: the scripts of the cards subscribe to it
 * (AbstractCardScript::getSubscribedEvents()), and an interrupt window opens before it occurs for the players whose
 * cards can interrupt it (InterruptOfferer).
 */
interface ImminentTriggerInterface
{
    /**
     * The name of the triggering condition (`card_leaving_play`), for the clients.
     */
    public static function getTriggerName(): string;

    /**
     * What the triggering condition names, by param: ids of cards and players, for the clients. `card`, when present,
     * is the card a cancel names (CancelImminentTrigger), `host` the card whose own condition causes this one.
     *
     * @return array<string, string>
     */
    public function getTriggerParams(): array;

    /**
     * The entry of the log written when the window opens, with its params (GameLogger).
     */
    public function getLogTemplate(): string;

    /**
     * @return array<string, mixed>
     */
    public function getLogParams(): array;
}
