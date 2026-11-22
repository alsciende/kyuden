<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\InterruptStage;
use App\Enum\PersonalHonor;
use App\Service\CardScript\CardScriptRegistry;
use App\Service\ControlService;
use App\Service\Interrupt\InterruptOfferer;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\CardLeavingPlay;

/**
 * The cards interrupting a card leaving play (doc/interrupts.md, 1).
 */
class CardLeavingPlayInterruptsTest extends AbstractKernelTestCase
{
    public function testEveryCardInterruptingACardLeavingPlaySubscribesToIt(): void
    {
        $registry = self::service(CardScriptRegistry::class);
        $codes = array_values(array_filter(
            $registry->getCodes(),
            fn (string $code): bool => array_key_exists(CardLeavingPlay::class, $registry->find($code)?->getSubscribedEvents() ?? []),
        ));

        self::assertEqualsCanonicalizing([
            '01-reprieve', '01-stand-your-ground', '04-back-alley-hideaway', '05-fawning-diplomat', '07-iron-mine',
            '08-fushicho', '10-tainted-koku', '13-callow-delegate', '15-seppun-truthseeker', '18-yasuki-fuzake',
            '19-fifth-tower-watch', '20-akodo-kaede', '22-sharpened-tsuruhashi', '24-gallant-quartermaster',
            '28-chancellor-s-aide', '29-pledge-of-loyalty', '30-disciple-of-shinsei', '30-traveling-philosopher',
            '33-beautiful-entertainer', '33-ceaseless-duty', '33-master-of-many-lifetimes', '34-agasha-sumiko',
            '36-daughter-of-war', '39-promising-youth',
        ], $codes);
    }

    private function scenarioWith(): Scenario
    {
        return $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['fushicho' => '08-fushicho', 'koku' => '10-tainted-koku'])
                ->inPlay(['quartermaster' => '24-gallant-quartermaster'])
                ->inPlay(['kaede' => '20-akodo-kaede'], fate: 2)
                ->inPlay(['honored' => '01-hida-kisada'], personalHonor: PersonalHonor::Honored)
                ->inPlay(['truthseeker' => '15-seppun-truthseeker'])
                ->hand(['ground' => '01-stand-your-ground']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['hotaru' => '01-doji-hotaru']))
            ->persist();
    }

    /**
     * The cards offered to each player, by alias and stage, for the card leaving play.
     *
     * @return array<string, list<string>> the aliases, by `player:stage`
     */
    private function offers(Scenario $scenario, string $alias, bool $sacrificed = false): array
    {
        $card = $scenario->card($alias);
        $controller = self::service(ControlService::class)->getController($card);
        if (! self::service(InterruptOfferer::class)->open($scenario->game(), [new CardLeavingPlay($card, $controller, true, $sacrificed)])) {
            return [];
        }

        $window = self::service(GameStateManager::class)->getState($scenario->game())->currentInterruptWindow();
        $offers = [];
        foreach ($window->stages ?? [] as $stage) {
            foreach ($stage->cardIds as $playerId => $cardIds) {
                $aliases = array_values(array_filter(
                    ['fushicho', 'koku', 'quartermaster', 'kaede', 'honored', 'truthseeker', 'ground', 'hotaru'],
                    fn (string $candidate): bool => in_array($scenario->card($candidate)->getId(), $cardIds, true),
                ));
                if ($aliases !== []) {
                    $player = $playerId === $scenario->player('john')->getId() ? 'john' : 'jane';
                    $offers["{$player}:{$stage->stage->value}"] = $aliases;
                }
            }
        }

        ksort($offers);

        return $offers;
    }

    public function testThisCharacterAndAttachedCharacterLeavingPlay(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame([
            'john:standard' => ['fushicho', 'koku'],
            // another character would leave play
            'john:would' => ['kaede'],
        ], $this->offers($scenario, 'fushicho'));
    }

    public function testASacrificedCharacter(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['john:standard' => ['quartermaster'], 'john:would' => ['kaede']], $this->offers($scenario, 'quartermaster', sacrificed: true));
        // Akodo Kaede: another character only
        self::assertSame([], $this->offers($scenario, 'kaede'));
    }

    public function testAnHonoredCharacterYouControl(): void
    {
        $scenario = $this->scenarioWith();

        // Stand Your Ground, in the hand
        self::assertSame(['john:would' => ['kaede', 'ground']], $this->offers($scenario, 'honored'));
    }

    public function testAForcedInterrupt(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['john:forced' => ['truthseeker'], 'john:would' => ['kaede']], $this->offers($scenario, 'truthseeker'));
    }

    public function testACharacterOfTheOpponent(): void
    {
        $scenario = $this->scenarioWith();

        // Akodo Kaede: another character, whoever controls it
        self::assertSame(['john:would' => ['kaede']], $this->offers($scenario, 'hotaru'));
    }
}
