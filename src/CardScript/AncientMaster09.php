<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Event\DefendersDeclaredEvent;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\PlayCharacterAsAttachment;
use App\Message\RevealCard;
use App\Message\ShuffleConflictDeck;
use App\Model\ReactionOffer;

/**
 * Ancient Master.
 */
final readonly class AncientMaster09 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '09-ancient-master';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, PlayCharacterAsAttachment::class, RevealCard::class, ShuffleConflictDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            DefendersDeclaredEvent::class => 'onDefendersDeclared',
            ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted',
        ];
    }

    /**
     * After attached character is declared as a defender.
     *
     * @return list<ReactionOffer>
     */
    public function onDefendersDeclared(DefendersDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && in_array($character, $event->defenders, true)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After attached character is declared as an attacker.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && in_array($character, $event->attackers, true)
            ? [new ReactionOffer($card)]
            : [];
    }
}
