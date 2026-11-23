<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Message\MoveConflict;
use App\Message\SwitchProvinceCards;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\ConflictDeclaringTrigger;

/**
 * Know the Terrain.
 */
final readonly class KnowTheTerrain24 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '24-know-the-terrain';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ConflictDeclaringTrigger::class => 'onConflictDeclaring'];
    }

    /**
     * When an attack is declared against a non-stronghold facedown province you control, before revealing that province, if you control another one: the provinces switched as their cards (SwitchProvinceCards) and the conflict moved (MoveConflict), once declared.
     *
     * @return list<InterruptOffer>
     */
    public function onConflictDeclaring(ConflictDeclaringTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        $player = $this->triggerConditions->getController($card);
        if ($trigger->defender !== $player || ! $this->isHiddenRegularProvince($trigger->province)) {
            return [];
        }

        foreach ($player->getStacks(StackType::RegularProvince) as $stack) {
            foreach ($stack->getCards() as $province) {
                if ($province !== $trigger->province && $this->isHiddenRegularProvince($province)) {
                    return [new InterruptOffer($card)];
                }
            }
        }

        return [];
    }

    private function isHiddenRegularProvince(PhysicalCard $province): bool
    {
        return $province instanceof ProvinceCard
            && $province->getStack()->getType() === StackType::RegularProvince
            && $province->getOrientation() === Orientation::FaceDown;
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [SwitchProvinceCards::class, MoveConflict::class];
    }
}
