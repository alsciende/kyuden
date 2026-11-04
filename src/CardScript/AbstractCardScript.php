<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ActionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What the text of a card means for a game in progress, one subclass per card that needs it (named after the code of
 * the card: KakitaKaezin01 for 01-kakita-kaezin), found by its code (CardScriptRegistry). Each method has a default
 * doing nothing, which a card overrides.
 *
 * A script informs the players (the actions offered, …), it never decides: the engine does not enforce the card rules.
 * It is a stateless service, shared by every game: what it needs comes from the arguments, the state of the game stays
 * in the game, so that replaying the events of a game gives the same game.
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
