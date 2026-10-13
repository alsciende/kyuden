<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\DynastyCard;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotEnoughFateException;
use App\Exception\Rules\NotOwnedCardException;
use App\Exception\Rules\WrongCardTypeException;
use App\Message\PlayCharacterFromProvince;
use App\MessageHandler\PlayCharacterFromProvinceHandler;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;

class PlayCharacterFromProvinceHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAtPlayCardsFromProvinces(bool $faceup = true, string $place = 'play_cards_from_provinces'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(7)
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi', dynastyFaceup: $faceup)
                ->province('01-entrenched-position', dynasty: '01-imperial-storehouse', dynastyAs: 'storehouse', dynastyFaceup: true))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', dynasty: '01-doji-hotaru', dynastyAs: 'hotaru', dynastyFaceup: true))
            ->activePlayer('john')
            ->persist();
    }

    private function play(Scenario $scenario, string $card, int $cardCost = 5, int $fateCost = 2, int $fate = 2, string $player = 'john'): void
    {
        self::service(PlayCharacterFromProvinceHandler::class)(new PlayCharacterFromProvince(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($card)->getId(),
            $cardCost,
            $fateCost,
            $fate,
        ));
    }

    public function testTheCharacterEntersPlayReadyWithTheFatePlacedOnIt(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->play($scenario, 'toturi');

        $toturi = $scenario->card('toturi');
        self::assertSame(DynastyCard::STATE_IN_PLAY, $toturi->getCurrentPlace());
        self::assertSame(StackType::InPlay, $toturi->getStack()->getType());
        self::assertSame(Orientation::FaceUp, $toturi->getOrientation());
        self::assertSame(2, self::service(CardStateManager::class)->getState($toturi)->getFate());
    }

    public function testThePlayerPaysTheCardCostAndTheFateCost(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->play($scenario, 'toturi', cardCost: 4, fateCost: 1, fate: 2);

        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertSame(2, self::service(CardStateManager::class)->getState($scenario->card('toturi'))->getFate());
    }

    public function testThePlayerKeepsTheOpportunityToActAndTheProvinceIsNotRefilled(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->play($scenario, 'toturi');

        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('john')));
        self::assertCount(1, $scenario->card('province')->getStack()->getCards());
    }

    public function testRejectsAPlayerWhoCannotPay(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->expectException(NotEnoughFateException::class);

        $this->play($scenario, 'toturi', cardCost: 5, fateCost: 3);
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->expectException(NotActivePlayerException::class);

        $this->play($scenario, 'hotaru', player: 'jane');
    }

    public function testRejectsACardOfAnotherPlayer(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->expectException(NotOwnedCardException::class);

        $this->play($scenario, 'hotaru');
    }

    public function testRejectsACardThatIsNotACharacter(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->expectException(WrongCardTypeException::class);

        $this->play($scenario, 'storehouse');
    }

    public function testRejectsAFacedownCard(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(faceup: false);

        $this->expectException(NotEnabledTransitionException::class);

        $this->play($scenario, 'toturi');
    }

    public function testRejectsNegativeCosts(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->expectException(\InvalidArgumentException::class);

        $this->play($scenario, 'toturi', fateCost: -1);
    }

    public function testRejectsAPlayOutsideThePlayCardsFromProvincesStep(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(place: 'collect_fate');

        $this->expectException(WorkflowException::class);

        $this->play($scenario, 'toturi');
    }
}
