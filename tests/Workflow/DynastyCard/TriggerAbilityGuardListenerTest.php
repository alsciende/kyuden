<?php

declare(strict_types=1);

namespace App\Tests\Workflow\DynastyCard;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class TriggerAbilityGuardListenerTest extends AbstractKernelTestCase
{
    private function canTriggerFromProvince(string $code): bool
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: $code, dynastyAs: 'card', dynastyFaceup: true))
            ->withPlayer('jane')
            ->build();
        $card = $scenario->card('card');

        return self::service(Registry::class)->get($card)->can($card, 'trigger_ability');
    }

    public function testAllowedForAFaceupHoldingInAProvince(): void
    {
        self::assertTrue($this->canTriggerFromProvince('01-imperial-storehouse'));
    }

    public function testBlockedForAFaceupCharacterInAProvince(): void
    {
        self::assertFalse($this->canTriggerFromProvince('01-akodo-toturi'));
    }
}
