<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\StackType;
use App\Event\TriggeringEventInterface;
use App\Trigger\ImminentTriggerInterface;
use App\Message\ActionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What the text of a card means for a game in progress, one subclass per card that needs it (named after the code of
 * the card: KakitaKaezin01 for 01-kakita-kaezin), found by its code (CardScriptRegistry). Each method has a default
 * doing nothing, which a card overrides.
 *
 * A script informs the players (the actions offered, …), it never decides: the engine does not enforce the card rules.
 * It is a stateless service, shared by every game: what it needs comes from the arguments, the state of the game stays
 * in the game, so that replaying the records of a game gives the same game.
 */
#[AutoconfigureTag]
abstract readonly class AbstractCardScript
{
    /**
     * The code of the card (LogicalCard id).
     */
    abstract public static function getCode(): string;

    /**
     * The timings of the triggered abilities printed on the card, including the abilities it grants to the card it is
     * attached to.
     *
     * @return list<AbilityTiming>
     */
    public function getAbilityTimings(): array
    {
        return [];
    }

    /**
     * The triggered abilities of the card are granted to the card it is attached to ("Attached character gains…"):
     * the controller of that card triggers them.
     */
    public function isAbilityGrantedToAttached(): bool
    {
        return false;
    }

    /**
     * The stacks out of play where the triggered abilities of the card are used, as its text says (Vengeful Oathkeeper:
     * "put this character into play from your hand"; a character face up in a province: Sneaky Shinjo). Elsewhere out
     * of play, its abilities cannot be triggered (App\Workflow\TriggerAbilityGuardListener); in play, they always can.
     *
     * @return list<StackType>
     */
    public function getAbilityStacksOutOfPlay(): array
    {
        return [];
    }

    /**
     * The triggering conditions the card answers: the method of the script called for each copy of the card its
     * controller can use where it is. The method reads the condition, the copy and the game, and changes nothing.
     *
     * - A reaction answers a domain event (TriggeringEventInterface), once dispatched (ReactionOfferer): it returns the
     *   reactions its controller may trigger now (list<ReactionOffer>, none when the condition is not met).
     *
     *       public function onProvinceRevealed(ProvinceRevealedEvent $event, PhysicalCard $card, Game $game): array
     *
     * - An interrupt answers an imminent condition (ImminentTriggerInterface), before it occurs (InterruptOfferer): it
     *   returns the interrupts its controller may trigger (list<InterruptOffer>). The condition is read on what every
     *   player sees: for a card out of sight, the copy may be in the conflict deck.
     *
     *       public function onCardLeavingPlay(CardLeavingPlay $trigger, PhysicalCard $card, Game $game): array
     *
     * Read when the container is compiled (App\Kernel).
     *
     * @return array<class-string<TriggeringEventInterface|ImminentTriggerInterface>, string> the name of the method, by
     *                                                                                      class of event or condition
     */
    public static function getSubscribedEvents(): array
    {
        return [];
    }

    /**
     * The special messages (AppAction::$special) the text of the card needs, offered by the web client to the player
     * whose active card it is (SpecialActionsResolver).
     *
     * @return list<class-string<ActionInterface>>
     */
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [];
    }
}
