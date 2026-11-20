<?php

declare(strict_types=1);

namespace App\Event;

/**
 * A domain event that is the triggering condition of abilities ("After a province is revealed…"): the scripts of the
 * cards subscribe to it (AbstractCardScript::getSubscribedEvents()), and a reaction window opens for the players whose
 * cards can react to it (ReactionOfferer).
 */
interface TriggeringEventInterface extends GameEventInterface
{
    /**
     * The name of the triggering condition (`province_revealed`), for the clients.
     */
    public static function getTriggerName(): string;

    /**
     * What the triggering condition names, by param: ids of cards and players, for the clients.
     *
     * @return array<string, string>
     */
    public function getTriggerParams(): array;
}
