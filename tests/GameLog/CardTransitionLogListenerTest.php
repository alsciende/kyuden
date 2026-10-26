<?php

declare(strict_types=1);

namespace App\Tests\GameLog;

use App\Enum\PersonalHonor;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

class CardTransitionLogListenerTest extends AbstractKernelTestCase
{
    private function scenarioWithCards(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->hand(['katana' => '01-fine-katana'])
                ->conflictDeck(['banzai' => '01-banzai'])
                ->dynastyDeck(['toturi' => '01-akodo-toturi'])
                ->province('01-entrenched-position', as: 'province')
                ->inPlay(['spear' => '01-master-of-the-spear'], fate: 2, personalHonor: PersonalHonor::Honored))
            ->withPlayer('jane')
            ->build();
    }

    /**
     * @param array<mixed> $context
     */
    private function apply(Scenario $scenario, string $alias, string $transition, array $context = []): void
    {
        $card = $scenario->card($alias);
        self::service(Registry::class)->get($card)->apply($card, $transition, $context);
    }

    public function testACardDrawnIsKnownToItsOwnerOnly(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->apply($scenario, 'banzai', 'draw_card');

        self::assertSame(['john draws Banzai!'], $this->logLines($scenario->game(), $scenario->player('john')));
        self::assertSame(['john draws a hidden card'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACardPlayedFromHandIsPublic(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->apply($scenario, 'katana', 'play_card', [
            'stack' => $scenario->card('spear')->getStack(),
        ]);

        self::assertSame(["john plays Fine Katana from john's hand into john's play area"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testACardPutBackIntoTheDeckFromHandIsKnownToItsOwnerOnly(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->apply($scenario, 'katana', 'put_back');

        self::assertSame(["Fine Katana is put from john's hand into john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('john')));
        self::assertSame(["a hidden card is put from john's hand into john's conflict deck"], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testADynastyCardPutFacedownIntoAProvinceIsKnownToNobody(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->apply($scenario, 'toturi', 'draw_card', [
            'stack' => $scenario->card('province')->getStack(),
        ]);

        self::assertSame(["a facedown card is put facedown into john's province"], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testACardTurnedFaceupIsPublic(): void
    {
        $scenario = $this->scenarioWithCards();
        $this->apply($scenario, 'toturi', 'draw_card', [
            'stack' => $scenario->card('province')->getStack(),
        ]);

        $this->apply($scenario, 'toturi', 'turn_faceup');

        self::assertSame("Akodo Toturi is turned faceup in john's province", $this->logLines($scenario->game(), $scenario->player('jane'))[1]);
    }

    public function testTheConsequencesOfLeavingPlayAreLoggedAfterTheMove(): void
    {
        $scenario = $this->scenarioWithCards();

        $this->apply($scenario, 'spear', 'discard_card');

        self::assertSame([
            "Master of the Spear is discarded from john's play area",
            'john gains 1 honor',
            '2 fate is removed from Master of the Spear',
            'Master of the Spear is now ordinary',
        ], $this->logLines($scenario->game()));
    }

    public function testEveryTransitionOfTheWorkflowsGivesItsLogTemplate(): void
    {
        foreach (['game', 'conflict_card', 'dynasty_card', 'province_card', 'role_card', 'stronghold_card'] as $name) {
            $workflow = self::getContainer()->get("state_machine.{$name}");
            self::assertInstanceOf(WorkflowInterface::class, $workflow);
            foreach ($workflow->getDefinition()->getTransitions() as $transition) {
                self::assertIsString($workflow->getMetadataStore()->getMetadata('log', $transition), "Transition {$transition->getName()} of workflow {$name} has no log template.");
            }
        }
    }
}
