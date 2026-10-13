<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInActionWindowException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\TriggerActionAbility;
use App\MessageHandler\TriggerActionAbilityHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class TriggerActionAbilityHandlerTest extends AbstractKernelTestCase
{
    private function scenarioInActionWindow(string $place = 'play_cards_from_provinces'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->province('01-entrenched-position', as: 'unbroken', orientation: Orientation::FaceUp, dynasty: '01-imperial-storehouse', dynastyAs: 'storehouse', dynastyFaceup: true)
                ->province('01-entrenched-position', as: 'facedown')
                ->hand([
                    'banzai' => '01-banzai',
                ])
                ->inPlay([
                    'toturi' => '01-akodo-toturi',
                    'katana' => '01-fine-katana',
                ]))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay([
                'hotaru' => '01-doji-hotaru',
            ]))
            ->activePlayer('john')
            ->persist();
    }

    private function trigger(Scenario $scenario, string $card, string $player = 'john'): void
    {
        self::service(TriggerActionAbilityHandler::class)(new TriggerActionAbility(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
        ));
    }

    public function testTheCardStaysWhereItIsAndThePlayerKeepsTheOpportunityToAct(): void
    {
        $scenario = $this->scenarioInActionWindow();
        $toturi = $scenario->card('toturi');
        $stack = $toturi->getStack();

        $this->trigger($scenario, 'toturi');

        self::assertSame(DynastyCard::STATE_IN_PLAY, $toturi->getCurrentPlace());
        self::assertSame($stack, $toturi->getStack());
        self::assertSame(Orientation::FaceUp, $toturi->getOrientation());
        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('john')));
    }

    public function testAcceptsTheCardsWhoseAbilitiesCanBeUsed(): void
    {
        $scenario = $this->scenarioInActionWindow();

        foreach (['katana', 'stronghold', 'unbroken', 'storehouse'] as $alias) {
            $card = $scenario->card($alias);
            $place = $card->getCurrentPlace();

            $this->trigger($scenario, $alias);

            self::assertSame($place, $card->getCurrentPlace(), $alias);
        }
    }

    public function testRejectsAFacedownProvince(): void
    {
        $scenario = $this->scenarioInActionWindow();

        $this->expectException(NotEnabledTransitionException::class);

        $this->trigger($scenario, 'facedown');
    }

    public function testRejectsACardInHand(): void
    {
        $scenario = $this->scenarioInActionWindow();

        $this->expectException(NotEnabledTransitionException::class);

        $this->trigger($scenario, 'banzai');
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioInActionWindow();

        $this->expectException(NotActivePlayerException::class);

        $this->trigger($scenario, 'hotaru', player: 'jane');
    }

    public function testRejectsACardOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioInActionWindow();

        $this->expectException(NotOwnedCardException::class);

        $this->trigger($scenario, 'hotaru');
    }

    public function testRejectsAGameStepThatIsNotAnActionWindow(): void
    {
        $scenario = $this->scenarioInActionWindow(place: 'collect_fate');

        $this->expectException(NotInActionWindowException::class);

        $this->trigger($scenario, 'toturi');
    }
}
