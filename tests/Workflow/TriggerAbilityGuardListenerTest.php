<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\PhysicalCard;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class TriggerAbilityGuardListenerTest extends AbstractKernelTestCase
{
    private function canTrigger(PhysicalCard $card): bool
    {
        return self::service(Registry::class)->get($card)->can($card, 'trigger_ability');
    }

    private function canTriggerFromProvince(string $code): bool
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: $code, dynastyAs: 'card', dynastyFaceup: true))
            ->withPlayer('jane')
            ->build();

        return $this->canTrigger($scenario->card('card'));
    }

    public function testAllowedForAFaceupHoldingInAProvince(): void
    {
        self::assertTrue($this->canTriggerFromProvince('01-imperial-storehouse'));
    }

    public function testBlockedForAFaceupCharacterInAProvince(): void
    {
        self::assertFalse($this->canTriggerFromProvince('01-akodo-toturi'));
    }

    public function testAllowedForACharacterInAProvinceWhenItsScriptSaysSo(): void
    {
        self::assertTrue($this->canTriggerFromProvince('10-sneaky-shinjo'));
    }

    public function testOutOfPlayOnlyInTheStacksTheScriptNames(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['in_play' => '01-vengeful-oathkeeper'])
                ->hand(['oathkeeper' => '01-vengeful-oathkeeper', 'other' => '01-fine-katana'])
                ->conflictDiscardPile(['discarded_oathkeeper' => '01-vengeful-oathkeeper'])
                ->dynastyDiscardPile(['yomi' => '27-champions-of-yomi', 'toturi' => '01-akodo-toturi']))
            ->withPlayer('jane')
            ->build();

        self::assertTrue($this->canTrigger($scenario->card('in_play')));
        self::assertTrue($this->canTrigger($scenario->card('oathkeeper')));
        self::assertTrue($this->canTrigger($scenario->card('yomi')));
        self::assertFalse($this->canTrigger($scenario->card('other')));
        self::assertFalse($this->canTrigger($scenario->card('discarded_oathkeeper')));
        self::assertFalse($this->canTrigger($scenario->card('toturi')));
    }
}
