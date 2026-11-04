<?php

declare(strict_types=1);

namespace App\Tests\Service\CardScript;

use App\CardScript\AbstractCardScript;
use App\Enum\AbilityTiming;
use App\Maker\MakeCardScript;
use App\Repository\LogicalCardRepository;
use App\Service\ActionMessageRegistry;
use App\Service\CardScript\CardScriptRegistry;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class CardScriptRegistryTest extends AbstractKernelTestCase
{
    public function testEveryScriptIsTheScriptOfACardNamedAfterItsCode(): void
    {
        $codes = self::service(CardScriptRegistry::class)->getCodes();
        self::assertNotEmpty($codes);

        foreach ($codes as $code) {
            self::assertNotNull(self::service(LogicalCardRepository::class)->find($code), "{$code} is not a card.");
        }

        // src/CardScript holds AbstractCardScript and the scripts only
        $files = glob(\dirname(__DIR__, 3) . '/src/CardScript/*.php') ?: [];
        self::assertCount(\count($codes) + 1, $files);
        foreach ($files as $file) {
            $className = 'App\\CardScript\\' . basename($file, '.php');
            if ($className !== AbstractCardScript::class) {
                self::assertTrue(is_subclass_of($className, AbstractCardScript::class), "{$className} is not a script.");
                self::assertSame(MakeCardScript::getClassName($className::getCode()), $className);
            }
        }
    }

    public function testTheScriptsOnlyNeedSpecialActions(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane')
            ->build();
        $registry = self::service(ActionMessageRegistry::class);
        $specialTypes = $registry->getSpecialMessageTypes();
        $card = $scenario->card('toturi');

        foreach (self::service(CardScriptRegistry::class)->getCodes() as $code) {
            // any card: the scripts are checked whatever the card
            $script = self::service(CardScriptRegistry::class)->find($code);
            self::assertInstanceOf(AbstractCardScript::class, $script);

            foreach ($script->getSpecialActions($card, $scenario->game()) as $className) {
                self::assertContains($registry->getMessageType($className), $specialTypes, "{$code} needs {$className}, which is not a special action.");
            }
        }
    }

    public function testTheAbilitiesOfACardComeFromItsScript(): void
    {
        $registry = self::service(CardScriptRegistry::class);

        self::assertSame([AbilityTiming::Action], $registry->getAbilityTimings('01-kakita-kaezin'));
        self::assertTrue($registry->hasAbilityTiming('01-kakita-kaezin', AbilityTiming::Action));
        self::assertFalse($registry->hasAbilityTiming('01-kakita-kaezin', AbilityTiming::Reaction));
        self::assertFalse($registry->isAbilityGrantedToAttached('01-kakita-kaezin'));
        self::assertSame([AbilityTiming::Action], $registry->getAbilityTimings('40-ancestral-sight'));
        self::assertTrue($registry->isAbilityGrantedToAttached('40-ancestral-sight'));
    }

    public function testACardWithoutScriptHasNoAbility(): void
    {
        $registry = self::service(CardScriptRegistry::class);

        self::assertNull($registry->find('01-aggressive-moto'));
        self::assertSame([], $registry->getAbilityTimings('01-aggressive-moto'));
        self::assertFalse($registry->isAbilityGrantedToAttached('01-aggressive-moto'));
    }
}
