<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\LogicalCard;
use App\Entity\Printing;
use App\View\Model\LogicalCardView;

/**
 * Builds the view of a card as printed: it is the same for everyone.
 */
final readonly class LogicalCardViewFactory
{
    public function create(LogicalCard $card): LogicalCardView
    {
        return new LogicalCardView(
            code: $card->getId(),
            name: $card->getName(),
            side: $card->getSide(),
            type: $card->getType(),
            clan: $card->getClan(),
            uniqueness: $card->getUniqueness(),
            cost: $card->getCost(),
            traits: $card->getTraits(),
            text: $card->getText(),
            allowedClans: $card->getAllowedClans() === null ? null : array_values($card->getAllowedClans()),
            deckLimit: $card->getDeckLimit(),
            roleRestriction: $card->getRoleRestriction(),
            influenceCost: $card->getInfluenceCost(),
            influencePool: $card->getInfluencePool(),
            elements: $card->getElements(),
            fate: $card->getFate(),
            honor: $card->getHonor(),
            glory: $card->getGlory(),
            military: $card->getMilitary(),
            militaryBonus: $card->getMilitaryBonus(),
            political: $card->getPolitical(),
            politicalBonus: $card->getPoliticalBonus(),
            strength: $card->getStrength(),
            strengthBonus: $card->getStrengthBonus(),
            abilityTimings: $card->getAbilityTimings(),
            abilityGrantedToAttached: $card->isAbilityGrantedToAttached(),
            imageUrl: $this->getImageUrl($card),
        );
    }

    /**
     * The engine does not use the images: it hands the clients the one of the first printing (by id) that has one.
     */
    private function getImageUrl(LogicalCard $card): ?string
    {
        $printings = $card->getPrintings()->toArray();
        usort($printings, static fn (Printing $a, Printing $b): int => $a->getId() <=> $b->getId());
        foreach ($printings as $printing) {
            if ($printing->getImageUrl() !== null) {
                return $printing->getImageUrl();
            }
        }

        return null;
    }
}
