<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictResolvedEvent;
use App\Message\DeclareNextConflict;
use App\Message\SwitchConflictType;
use App\Model\ReactionOffer;

/**
 * Ivory Kingdoms Unicorn.
 */
final readonly class IvoryKingdomsUnicorn17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-ivory-kingdoms-unicorn';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DeclareNextConflict::class, SwitchConflictType::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictResolvedEvent::class => 'onConflictResolved'];
    }

    /**
     * After the resolution of a conflict in which this character was attacking, if a province was broken in the conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictResolved(ConflictResolvedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->wasAttacking($card)
            && $event->brokenProvinceIds !== []
            ? [new ReactionOffer($card)]
            : [];
    }
}
